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
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function createGroupForTeacher(string $organizationId, string $staffProfileId, string $courseId): string
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

    return $groupId;
}

function addStudentToGroup(string $groupId, string $studentProfileId): void
{
    DB::table('group_memberships')->insert([
        'id' => (string) Str::ulid(),
        'group_id' => $groupId,
        'student_profile_id' => $studentProfileId,
        'joined_at' => now(),
        'status' => 'active',
        'created_at' => now(),
    ]);
}

it('lists the teachers groups with course and student count', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $courseId = Fixtures::courseId();

    $groupId = createGroupForTeacher($organizationId, $staffProfileId, $courseId);
    addStudentToGroup($groupId, Fixtures::studentProfileId());
    addStudentToGroup($groupId, Fixtures::studentProfileId());

    $response = $this->actingAs($teacher)->getJson('/api/teacher/groups');

    $response->assertOk();
    $groups = $response->json('groups');
    expect($groups)->toHaveCount(1)
        ->and($groups[0]['id'])->toBe($groupId)
        ->and($groups[0]['studentsCount'])->toBe(2);
});

it('returns an empty list for a teacher with no groups', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/groups');

    $response->assertOk()->assertJsonPath('groups', []);
});

it('shows group detail with its students and upcoming sessions', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $courseId = Fixtures::courseId();

    $groupId = createGroupForTeacher($organizationId, $staffProfileId, $courseId);
    $studentId = Fixtures::studentProfileId();
    addStudentToGroup($groupId, $studentId);

    $response = $this->actingAs($teacher)->getJson("/api/teacher/groups/{$groupId}");

    $response->assertOk();
    $group = $response->json('group');
    expect($group['id'])->toBe($groupId)
        ->and($group['students'])->toHaveCount(1)
        ->and($group['students'][0]['id'])->toBe($studentId)
        ->and($group['sessions'])->toBeArray();
});

it('returns 404 for a group that belongs to a different teacher', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());
    $groupId = createGroupForTeacher($organizationId, $otherStaffProfileId, Fixtures::courseId());

    $this->actingAs($teacher)
        ->getJson("/api/teacher/groups/{$groupId}")
        ->assertNotFound();
});

it('returns 404 for a teacher with no staff profile', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();

    $this->actingAs($teacher)
        ->getJson('/api/teacher/groups/'.(string) Str::ulid())
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/teacher/groups')->assertUnauthorized();
    $this->getJson('/api/teacher/groups/'.(string) Str::ulid())->assertUnauthorized();
});
