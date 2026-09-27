<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function insertIndividualSchedule(string $organizationId, string $staffProfileId, string $studentProfileId, string $courseId): void
{
    DB::table('schedules')->insert([
        'id' => (string) Str::ulid(),
        'organization_id' => $organizationId,
        'group_id' => null,
        'student_profile_id' => $studentProfileId,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'session_type' => 'individual',
        'rrule' => 'FREQ=WEEKLY',
        'start_time' => '09:00:00',
        'duration_minutes' => 30,
        'timezone' => 'UTC',
        'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
        'materialized_until' => CarbonImmutable::now('UTC')->addDays(30)->toDateString(),
        'is_active' => true,
        'created_by' => Fixtures::userId(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertGroupWithStudent(string $organizationId, string $staffProfileId, string $studentProfileId, string $courseId): void
{
    $groupId = (string) Str::ulid();

    DB::table('groups')->insert([
        'id' => $groupId,
        'organization_id' => $organizationId,
        'code' => 'G-'.strtoupper(substr($groupId, -6)),
        'name' => json_encode(['ar' => 'مجموعة الاختبار', 'en' => 'Test Group'], JSON_UNESCAPED_UNICODE),
        'capacity' => 10,
        'timezone' => 'UTC',
        'status' => 'active',
        'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('group_teachers')->insert([
        'id' => (string) Str::ulid(),
        'group_id' => $groupId,
        'staff_profile_id' => $staffProfileId,
        'course_id' => $courseId,
        'role' => 'lead',
        'assigned_from' => CarbonImmutable::now('UTC')->toDateString(),
        'created_at' => now(),
    ]);

    DB::table('group_memberships')->insert([
        'id' => (string) Str::ulid(),
        'group_id' => $groupId,
        'student_profile_id' => $studentProfileId,
        'joined_at' => now(),
        'status' => 'active',
        'created_at' => now(),
    ]);
}

it('lists a student reachable only through an individual schedule (no group)', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();

    insertIndividualSchedule($organizationId, $staffProfileId, $studentProfileId, $courseId);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/students');

    $response->assertOk();
    $students = $response->json('students');
    expect($students)->toHaveCount(1)
        ->and($students[0]['id'])->toBe($studentProfileId)
        ->and($students[0]['via'])->toBe('individual');
});

it('lists a student reachable through a group', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();

    insertGroupWithStudent($organizationId, $staffProfileId, $studentProfileId, $courseId);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/students');

    $response->assertOk();
    $students = $response->json('students');
    expect($students)->toHaveCount(1)
        ->and($students[0]['id'])->toBe($studentProfileId)
        ->and($students[0]['via'])->toBe('group');
});

it('does not duplicate a student reachable via both a group and an individual schedule', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();

    insertGroupWithStudent($organizationId, $staffProfileId, $studentProfileId, $courseId);
    insertIndividualSchedule($organizationId, $staffProfileId, $studentProfileId, $courseId);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/students');

    $response->assertOk();
    expect($response->json('students'))->toHaveCount(1);
});

it('returns an empty roster for a teacher with no students', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/students');

    $response->assertOk()->assertJsonPath('students', []);
});

it('shows detail for a student reachable only through an individual schedule', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();

    insertIndividualSchedule($organizationId, $staffProfileId, $studentProfileId, $courseId);

    $response = $this->actingAs($teacher)->getJson("/api/teacher/students/{$studentProfileId}");

    $response->assertOk()
        ->assertJsonPath('student.id', $studentProfileId)
        ->assertJsonPath('student.groups', [])
        ->assertJsonCount(1, 'student.courses');
});

it('shows detail for a student reachable only through a group', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();

    insertGroupWithStudent($organizationId, $staffProfileId, $studentProfileId, $courseId);

    $response = $this->actingAs($teacher)->getJson("/api/teacher/students/{$studentProfileId}");

    $response->assertOk()
        ->assertJsonPath('student.id', $studentProfileId)
        ->assertJsonPath('student.courses', [])
        ->assertJsonCount(1, 'student.groups');
});

it('returns 404 for a student not linked to this teacher at all', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);
    $unrelatedStudentId = Fixtures::studentProfileId();

    $this->actingAs($teacher)
        ->getJson("/api/teacher/students/{$unrelatedStudentId}")
        ->assertNotFound();
});

it('returns 404 for a student linked to a different teacher only', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();
    insertIndividualSchedule($organizationId, $otherStaffProfileId, $studentProfileId, $courseId);

    $this->actingAs($teacher)
        ->getJson("/api/teacher/students/{$studentProfileId}")
        ->assertNotFound();
});

it('never leaks a student from a different organization even if a schedule row wrongly links them', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $otherOrganizationId = (string) Str::ulid();
    DB::table('organizations')->insert([
        'id' => $otherOrganizationId,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other Org'], JSON_UNESCAPED_UNICODE),
        'slug' => 'other-org-'.strtolower(substr($otherOrganizationId, -10)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherOrgUserId = (string) Str::ulid();
    DB::table('users')->insert([
        'id' => $otherOrgUserId,
        'organization_id' => $otherOrganizationId,
        'name' => 'Other Org Student User',
        'email' => 'other.org.student@test.local',
        'profile_completed_at' => now(),
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherOrgStudentId = (string) Str::ulid();
    DB::table('student_profiles')->insert([
        'id' => $otherOrgStudentId,
        'organization_id' => $otherOrganizationId,
        'user_id' => $otherOrgUserId,
        'student_code' => 'OTHERORG-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // صف بيانات فاسد عمدًا: جدول بمنظمة المعلم لكن طالبه من منظمة أخرى —
    // يتحقق إن الاستعلام يحمي بالربط الصريح لا بثقة عمياء في بيانات الجدول.
    $courseId = Fixtures::courseId();
    insertIndividualSchedule($organizationId, $staffProfileId, $otherOrgStudentId, $courseId);

    $this->actingAs($teacher)
        ->getJson("/api/teacher/students/{$otherOrgStudentId}")
        ->assertNotFound();

    $listResponse = $this->actingAs($teacher)->getJson('/api/teacher/students');
    $listResponse->assertOk();
    expect(collect($listResponse->json('students'))->pluck('id'))
        ->not->toContain($otherOrgStudentId);
});

it('requires authentication', function (): void {
    $this->getJson('/api/teacher/students')->assertUnauthorized();
    $this->getJson('/api/teacher/students/'.(string) Str::ulid())->assertUnauthorized();
});
