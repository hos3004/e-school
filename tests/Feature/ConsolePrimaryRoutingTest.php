<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsolePrimaryRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        // Panel paths and route aliases are registered during application boot.
        // Override only this test process, preserving the repository's database
        // isolation guard and restoring the original environment immediately.
        $overrides = [
            'CONSOLE_ENABLED' => $this->name() === 'test_console_off_preserves_the_legacy_panel' ? 'false' : 'true',
            'CONSOLE_PRIMARY' => $this->name() === 'test_preview_mode_preserves_legacy_entry_points' ? 'false' : 'true',
        ];
        $saved = [];
        foreach ($overrides as $key => $value) {
            $saved[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        try {
            return parent::createApplication();
        } finally {
            foreach ($saved as $key => [$process, $environment, $server]) {
                putenv($process === false ? $key : $key.'='.$process);
                if ($environment === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $environment;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
    }

    public function test_primary_admin_entry_and_login_use_manage_while_v2_keeps_the_real_panel(): void
    {
        $admin = $this->account('platform_admin');
        $this->assertSame('v2', Filament::getPanel('admin')->getPath());
        $this->assertSame('v2', Route::getRoutes()->getByName('filament.admin.pages.dashboard')->uri());
        $this->get('/admin')->assertRedirect('/login')->assertSessionHas('url.intended', route('console.home'));
        $this->post('/login', ['login' => $admin->username, 'password' => 'password'])->assertRedirect('/manage');
        $this->get('/')->assertRedirect('/manage');
        $this->get('/admin/login')->assertRedirect('/manage');
        $this->get('/manage')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('Console/Workspace')->where('console.navigation', fn ($items): bool => collect($items)->contains(
                fn ($item): bool => $item['key'] === 'legacy' && $item['href'] === '/v2' && $item['fullPage'] === true,
            )));
        $this->get('/v2')->assertOk()->assertSee('العودة إلى اللوحة الجديدة');
    }

    public function test_legacy_deep_links_preserve_query_and_never_redirect_to_an_external_host(): void
    {
        $admin = $this->account('platform_admin');
        $this->actingAs($admin);
        $query = ['tableFilters' => ['status' => ['value' => 'active']], 'tableSearch' => 'طالب تجريبي'];
        $response = $this->get('/admin/students?'.http_build_query($query))->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertSame('/v2/students', parse_url($location, PHP_URL_PATH));
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($location, PHP_URL_HOST));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $actual);
        $this->assertSame($query, $actual);
        $this->get($location)->assertOk();
        $attempt = $this->get('/admin/https://outside.invalid/path?next=https%3A%2F%2Foutside.invalid')->assertRedirect();
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($attempt->headers->get('Location'), PHP_URL_HOST));
        $this->assertStringStartsWith('/v2/', (string) parse_url($attempt->headers->get('Location'), PHP_URL_PATH));
        $this->post('/admin/students')->assertStatus(405);
    }

    public function test_primary_portals_select_only_authorized_profiles_and_keep_deep_and_write_routes(): void
    {
        $student = $this->account('student');
        $this->actingAs($student)->get('/student')->assertRedirect('/learn/student');
        $this->get('/student/profile')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Student/Profile'));
        $this->get('/manage')->assertForbidden();
        $this->get('/v2')->assertForbidden();
        $this->get('/learn/teacher')->assertForbidden();
        $this->assertSame('student/assignments/{assignment}/submit', Route::getRoutes()->getByName('portal.student.assignments.submit')->uri());
        $this->assertSame('teacher/sessions/{session}/attendance', Route::getRoutes()->getByName('portal.teacher.sessions.attendance.store')->uri());
        $teacher = $this->account('teacher');
        $this->actingAs($teacher)->get('/teacher')->assertRedirect('/learn/teacher');
        $this->get('/teacher/profile')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Teacher/Profile'));
        $this->get('/learn/student')->assertForbidden();
        $teacher->update(['status' => UserStatus::Suspended]);
        $this->get('/learn/teacher')->assertForbidden();
        $this->get('/v2')->assertForbidden();
    }

    public function test_preview_mode_preserves_legacy_entry_points(): void
    {
        $this->assertFalse((bool) config('console.primary'));
        $this->assertSame('admin', Filament::getPanel('admin')->getPath());
        $this->assertNull(Route::getRoutes()->getByName('console.primary.legacy'));
        $this->actingAs($this->account('student'))->get('/student')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Student/Dashboard'));
        $this->actingAs($this->account('platform_admin'))->get('/admin')->assertOk();
        $this->get('/manage')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('console.navigation', fn ($items): bool => !collect($items)->contains('key', 'legacy')));
    }

    public function test_console_off_preserves_the_legacy_panel(): void
    {
        $this->assertFalse((bool) config('console.enabled'));
        $this->assertSame('admin', Filament::getPanel('admin')->getPath());
        $this->assertNull(Route::getRoutes()->getByName('console.primary.legacy'));
        $this->actingAs($this->account('student'))->get('/student')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Student/Dashboard'));
        $this->get('/learn/student')->assertNotFound();
        $this->get('/manage')->assertNotFound();
    }

    private function account(string $role): User
    {
        $user = User::factory()->create();
        if ($role === 'student') {
            StudentProfile::factory()->create(['organization_id' => $user->organization_id, 'user_id' => $user->id]);
        }
        if ($role === 'teacher') {
            StaffProfile::query()->create(['organization_id' => $user->organization_id, 'user_id' => $user->id, 'staff_code' => 'PRIMARY-'.$user->id, 'employment_type' => 'part_time']);
        }
        app(RoleAssignmentGateway::class)->assignIfMissing($role, User::class, $user->id, $user->organization_id);

        return $user;
    }
}
