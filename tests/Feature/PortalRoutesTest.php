<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PortalRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function portalRoutes(): array
    {
        return [
            'student dashboard' => ['portal.student.dashboard', 'student', 'session.view'],
            'student schedule' => ['portal.student.schedule', 'student/schedule', 'schedule.view'],
            'student session' => ['portal.student.sessions.show', 'student/sessions/{id}', 'session.view'],
            'student assignments' => ['portal.student.assignments.index', 'student/assignments', 'assignment.submit'],
            'student reports' => ['portal.student.reports.index', 'student/reports', 'session_report.view'],
            'teacher dashboard' => ['portal.teacher.dashboard', 'teacher', 'session.view'],
            'teacher schedule' => ['portal.teacher.schedule', 'teacher/schedule', 'schedule.view'],
            'teacher session' => ['portal.teacher.sessions.show', 'teacher/sessions/{id}', 'attendance.record'],
            'teacher postponements' => ['portal.teacher.postponements.index', 'teacher/postponements', 'session.postpone.approve'],
            'guardian dashboard' => ['portal.guardian.dashboard', 'guardian', 'student.view'],
            'guardian attendance' => ['portal.guardian.children.attendance', 'guardian/children/{studentId}/attendance', 'attendance.view'],
            'guardian reports' => ['portal.guardian.children.reports', 'guardian/children/{studentId}/reports', 'session_report.view'],
        ];
    }

    public function test_home_route_is_registered_as_the_thirteenth_portal_route(): void
    {
        $route = Route::getRoutes()->getByName('home');

        $this->assertNotNull($route);
        $this->assertSame('/', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    #[DataProvider('portalRoutes')]
    public function test_portal_routes_match_the_required_auth_and_permission_contract(
        string $name,
        string $uri,
        string $ability,
    ): void {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route);
        $this->assertSame($uri, $route->uri());
        $this->assertContains('GET', $route->methods());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('can:'.$ability, $route->middleware());
    }

    public function test_student_portal_requires_authentication_and_permission(): void
    {
        $this->get('/student')->assertRedirect(route('login'));

        $this->actingAs($this->portalUser())
            ->get('/student')
            ->assertForbidden();
    }

    public function test_student_dashboard_returns_the_empty_inertia_contract_and_shared_props(): void
    {
        Gate::define('session.view', static fn (): bool => true);
        $user = $this->portalUser();

        $response = $this->actingAs($user)->get('/student');

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Student/Dashboard')
                ->where('nextSession', null)
                ->has('weekSessions', 0)
                ->where('attendanceRate', null)
                ->has('openAssignments', 0)
                ->where('auth.user.id', (string) $user->getKey())
                ->where('auth.user.roles', [])
                ->where('locale', 'ar')
                ->where('direction', 'rtl'));

        $translations = $response->inertiaProps('translations');

        $this->assertIsArray($translations);
        $this->assertSame('لوحة الطالب', $translations['student.dashboard.title'] ?? null);
    }

    public function test_student_role_from_database_opens_the_student_portal_without_a_gate_stub(): void
    {
        $organizationId = $this->createOrganization();
        $this->seed(AccessControlSeeder::class);

        $user = $this->persistedUser($organizationId, [
            'email' => 'student.portal@example.test',
        ]);
        $studentProfileId = (string) Str::ulid();

        DB::table('student_profiles')->insert([
            'id' => $studentProfileId,
            'organization_id' => $organizationId,
            'user_id' => (string) $user->getKey(),
            'student_code' => 'PORTAL-STUDENT-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentRoleId = DB::table('roles')
            ->whereNull('organization_id')
            ->where('guard_name', 'web')
            ->where('name', 'student')
            ->value('id');

        $this->assertIsString($studentRoleId);

        DB::table('model_has_roles')->insert([
            'role_id' => $studentRoleId,
            'model_type' => User::class,
            'model_id' => (string) $user->getKey(),
        ]);

        app(PermissionGateRegistrar::class)->register();

        $this->actingAs($user)
            ->get('/student')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Student/Dashboard')
                ->where('auth.user.roles', ['student']));
    }

    public function test_locale_route_requires_authentication(): void
    {
        $this->post('/locale', ['locale' => 'en'])
            ->assertRedirect(route('login'));
    }

    public function test_supported_locale_is_persisted_and_shared_by_inertia(): void
    {
        $organizationId = $this->createOrganization();
        $user = $this->persistedUser($organizationId);

        Gate::define('session.view', static fn (): bool => true);

        $this->actingAs($user)
            ->from('/student')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/student')
            ->assertSessionHas('locale', 'en');

        $this->assertSame('en', $user->fresh()->locale);

        $response = $this->actingAs($user->fresh())->get('/student');

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Student/Dashboard')
                ->where('auth.user.locale', 'en')
                ->where('locale', 'en')
                ->where('direction', 'ltr'));

        $translations = $response->inertiaProps('translations');

        $this->assertIsArray($translations);
        $this->assertSame('Student dashboard', $translations['student.dashboard.title'] ?? null);
    }

    public function test_unsupported_locale_is_rejected_without_changing_user_or_session(): void
    {
        $organizationId = $this->createOrganization();
        $user = $this->persistedUser($organizationId);

        $this->actingAs($user)
            ->postJson('/locale', ['locale' => 'xx'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        $this->assertSame('ar', $user->fresh()->locale);
        $this->assertNull(session('locale'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function guardianScopedRoutes(): array
    {
        return [
            'attendance' => ['attendance.view', '/guardian/children/%s/attendance'],
            'reports' => ['session_report.view', '/guardian/children/%s/reports'],
        ];
    }

    #[DataProvider('guardianScopedRoutes')]
    public function test_guardian_child_routes_hide_students_without_a_verified_link(
        string $ability,
        string $uri,
    ): void {
        Gate::define($ability, static fn (): bool => true);

        $this->actingAs($this->portalUser())
            ->get(sprintf($uri, (string) Str::ulid()))
            ->assertNotFound();
    }

    public function test_verified_guardian_link_cannot_cross_organization_boundary(): void
    {
        $guardianOrganizationId = $this->createOrganization('guardian');
        $studentOrganizationId = $this->createOrganization('student');
        $guardianUser = $this->persistedUser($guardianOrganizationId, [
            'email' => 'guardian.portal@example.test',
        ]);
        $studentUser = $this->persistedUser($studentOrganizationId, [
            'email' => 'cross-org-student@example.test',
        ]);
        $guardianProfileId = (string) Str::ulid();
        $studentProfileId = (string) Str::ulid();

        DB::table('guardian_profiles')->insert([
            'id' => $guardianProfileId,
            'organization_id' => $guardianOrganizationId,
            'user_id' => (string) $guardianUser->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('student_profiles')->insert([
            'id' => $studentProfileId,
            'organization_id' => $studentOrganizationId,
            'user_id' => (string) $studentUser->getKey(),
            'student_code' => 'CROSS-ORG-STUDENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('guardian_links')->insert([
            'id' => (string) Str::ulid(),
            'guardian_profile_id' => $guardianProfileId,
            'student_profile_id' => $studentProfileId,
            'relationship' => 'father',
            'is_primary' => true,
            'can_act_for' => true,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Gate::define('student.view', static fn (): bool => true);
        Gate::define('attendance.view', static fn (): bool => true);

        $this->actingAs($guardianUser)
            ->get('/guardian')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Guardian/Dashboard')
                ->has('children', 0)
                ->where('selectedChild', null));

        $this->actingAs($guardianUser)
            ->get('/guardian/children/'.$studentProfileId.'/attendance')
            ->assertNotFound();
    }

    private function portalUser(): User
    {
        $user = new User;
        $user->forceFill([
            'id' => (string) Str::ulid(),
            'name' => 'مستخدم البوابة',
            'email' => 'portal@example.test',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
        ]);

        return $user;
    }

    private function createOrganization(string $label = 'portal'): string
    {
        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مدرسة البوابة', 'en' => 'Portal School'], JSON_THROW_ON_ERROR),
            'slug' => $label.'-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function persistedUser(string $organizationId, array $attributes = []): User
    {
        return User::factory()
            ->inOrganization($organizationId)
            ->create($attributes);
    }
}
