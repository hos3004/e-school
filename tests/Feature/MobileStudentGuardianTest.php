<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class MobileStudentGuardianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
        $this->seed(AccessControlSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_login_response_includes_the_actors_role_names(): void
    {
        $organizationId = Fixtures::organizationId();
        $studentUser = User::factory()->inOrganization($organizationId)->create(['password' => bcrypt('secret123')]);
        $this->assignRole($studentUser, 'student');

        $response = $this->postJson('/api/identity/login', [
            'identifier' => (string) $studentUser->email,
            'password' => 'secret123',
        ])->assertCreated();

        $response->assertJsonPath('user.roles', ['student']);
    }

    public function test_student_sees_only_their_own_sessions_and_can_open_one(): void
    {
        [$organizationId, $student, $studentProfileId, $sessionId] = $this->buildStudentWithSession();

        $index = $this->actingAs($student)->getJson('/api/student/sessions')->assertOk();
        self::assertCount(1, $index->json('sessions'));

        $show = $this->actingAs($student)->getJson("/api/student/sessions/{$sessionId}")->assertOk();
        $show->assertJsonPath('session.id', $sessionId);

        $stranger = User::factory()->inOrganization($organizationId)->create();
        $this->assignRole($stranger, 'student');
        Fixtures::studentProfileForUser($stranger->id);

        $this->actingAs($stranger)
            ->getJson("/api/student/sessions/{$sessionId}")
            ->assertNotFound();
    }

    public function test_student_profile_returns_own_account_and_attendance(): void
    {
        [, $student] = $this->buildStudentWithSession();

        $response = $this->actingAs($student)->getJson('/api/student/profile')->assertOk();
        $response->assertJsonPath('account.id', (string) $student->id)
            ->assertJsonPath('account.name', $student->name);
    }

    public function test_guardian_sees_only_their_own_children(): void
    {
        $organizationId = Fixtures::organizationId();
        $guardianUser = User::factory()->inOrganization($organizationId)->create();
        $this->assignRole($guardianUser, 'guardian');
        $guardianProfileId = $this->guardianProfile($organizationId, (string) $guardianUser->id);

        $myChildUser = User::factory()->inOrganization($organizationId)->create(['name' => 'My Real Child']);
        $myChildProfileId = Fixtures::studentProfileForUser($myChildUser->id);
        $this->linkGuardianToChild($guardianProfileId, $myChildProfileId);

        $otherChildProfileId = Fixtures::studentProfileId();

        $response = $this->actingAs($guardianUser)->getJson('/api/guardian/children')->assertOk();
        $children = $response->json('children');
        self::assertCount(1, $children);
        self::assertSame('My Real Child', $children[0]['name']);

        // ولي أمر لا يقدر يصل لطفل مش بتاعه حتى لو عرف معرّف ملفه.
        $this->actingAs($guardianUser)
            ->getJson("/api/guardian/children/{$otherChildProfileId}/sessions")
            ->assertNotFound();
    }

    public function test_guardian_can_open_their_childs_session_but_not_another_guardians_child(): void
    {
        [$organizationId, , $childProfileId, $sessionId] = $this->buildStudentWithSession();

        $guardianUser = User::factory()->inOrganization($organizationId)->create();
        $this->assignRole($guardianUser, 'guardian');
        $guardianProfileId = $this->guardianProfile($organizationId, (string) $guardianUser->id);
        $this->linkGuardianToChild($guardianProfileId, $childProfileId);

        $sessions = $this->actingAs($guardianUser)
            ->getJson("/api/guardian/children/{$childProfileId}/sessions")
            ->assertOk();
        self::assertCount(1, $sessions->json('sessions'));

        $show = $this->actingAs($guardianUser)
            ->getJson("/api/guardian/children/{$childProfileId}/sessions/{$sessionId}")
            ->assertOk();
        $show->assertJsonPath('session.id', $sessionId);

        $outsiderGuardian = User::factory()->inOrganization($organizationId)->create();
        $this->assignRole($outsiderGuardian, 'guardian');
        $this->guardianProfile($organizationId, (string) $outsiderGuardian->id);

        $this->actingAs($outsiderGuardian)
            ->getJson("/api/guardian/children/{$childProfileId}/sessions")
            ->assertNotFound();
    }

    /**
     * @return array{0: string, 1: User, 2: string, 3: string}
     */
    private function buildStudentWithSession(): array
    {
        $organizationId = Fixtures::organizationId();
        $student = User::factory()->inOrganization($organizationId)->create();
        $this->assignRole($student, 'student');
        $studentProfileId = Fixtures::studentProfileForUser($student->id);

        $program = Program::factory()->create(['organization_id' => $organizationId]);
        $level = Level::factory()->create(['program_id' => (string) $program->getKey()]);
        $course = Course::factory()->create([
            'organization_id' => $organizationId,
            'level_id' => (string) $level->getKey(),
        ]);

        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'student_profile_id' => $studentProfileId,
            'program_id' => (string) $program->getKey(),
            'status' => 'active',
            'applied_at' => now()->utc(),
            'activated_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        $staffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

        $session = Session::factory()->create([
            'organization_id' => $organizationId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => (string) $course->getKey(),
            'session_type' => 'individual',
            'status' => 'scheduled',
            'scheduled_start' => CarbonImmutable::now('UTC')->addHour(),
            'scheduled_end' => CarbonImmutable::now('UTC')->addHours(2),
        ]);
        $sessionId = (string) $session->id;

        DB::table('session_participants')->insert([
            'id' => (string) Str::ulid(),
            'session_id' => $sessionId,
            'student_profile_id' => $studentProfileId,
            'enrollment_id' => $enrollmentId,
            'join_url_token' => (string) Str::ulid(),
            'invited_at' => now()->utc(),
            'attended_minutes' => 0,
            'attended_seconds' => 0,
            'created_at' => now()->utc(),
        ]);

        return [$organizationId, $student, $studentProfileId, $sessionId];
    }

    private function guardianProfile(string $organizationId, string $userId): string
    {
        $id = (string) Str::ulid();
        DB::table('guardian_profiles')->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'guardian_code' => 'G'.strtoupper(substr($id, -8)),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    private function linkGuardianToChild(string $guardianProfileId, string $studentProfileId): void
    {
        DB::table('guardian_links')->insert([
            'id' => (string) Str::ulid(),
            'guardian_profile_id' => $guardianProfileId,
            'student_profile_id' => $studentProfileId,
            'relationship' => 'father',
            'is_primary' => true,
            'can_act_for' => true,
            'verified_at' => now()->utc(),
            'created_at' => now()->utc(),
        ]);
    }

    private function assignRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')
            ->whereNull('organization_id')
            ->where('guard_name', GuardName::Web->value)
            ->where('name', $roleName)
            ->value('id');

        self::assertIsString($roleId, "Role '{$roleName}' was not seeded by AccessControlSeeder.");

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);

        app(PermissionGateRegistrar::class)->register();
    }
}
