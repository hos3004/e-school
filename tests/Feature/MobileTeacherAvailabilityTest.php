<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('lists an empty availability set for a teacher with no rows yet', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/availability');

    $response->assertOk()
        ->assertJsonPath('availability', [])
        ->assertJsonPath('has_profile', true);
});

it('creates an availability window for the authenticated teacher only', function (): void {
    Gate::define('staff.availability.create', fn (): bool => true);

    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

    $response = $this->actingAs($teacher)->postJson('/api/teacher/availability', [
        // قيمة مدسوسة تستهدف ملف معلم آخر — يجب أن تُتجاهل تمامًا.
        'staff_profile_id' => $otherStaffProfileId,
        'weekday' => 2,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'timezone' => 'Africa/Cairo',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
    ]);

    $response->assertCreated();

    expect(DB::table('teacher_availability')->where('staff_profile_id', $staffProfileId)->count())->toBe(1)
        ->and(DB::table('teacher_availability')->where('staff_profile_id', $otherStaffProfileId)->count())->toBe(0);
});

it('rejects overlapping availability windows', function (): void {
    Gate::define('staff.availability.create', fn (): bool => true);

    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    DB::table('teacher_availability')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $staffProfileId,
        'weekday' => 2,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'timezone' => 'Africa/Cairo',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved->value,
        'created_at' => now(),
    ]);

    $this->actingAs($teacher)->postJson('/api/teacher/availability', [
        'weekday' => 2,
        'start_time' => '17:00',
        'end_time' => '19:00',
        'timezone' => 'Africa/Cairo',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
    ])->assertUnprocessable();
});

it('requires the availability.create permission to store a window', function (): void {
    Gate::define('staff.availability.create', fn (): bool => false);

    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $this->actingAs($teacher)->postJson('/api/teacher/availability', [
        'weekday' => 1,
        'start_time' => '10:00',
        'end_time' => '11:00',
        'timezone' => 'UTC',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
    ])->assertForbidden();
});

it('lets a teacher remove their own pending availability but not an approved one', function (): void {
    config()->set('scheduling.availability.teacher_requires_approval', true);

    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $pendingId = (string) Str::ulid();
    DB::table('teacher_availability')->insert([
        'id' => $pendingId,
        'staff_profile_id' => $staffProfileId,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'timezone' => 'UTC',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
        'approval_status' => TeacherAvailabilityApprovalStatus::Pending->value,
        'created_at' => now(),
    ]);

    $approvedId = (string) Str::ulid();
    DB::table('teacher_availability')->insert([
        'id' => $approvedId,
        'staff_profile_id' => $staffProfileId,
        'weekday' => 3,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'timezone' => 'UTC',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved->value,
        'created_at' => now(),
    ]);

    $this->actingAs($teacher)->deleteJson("/api/teacher/availability/{$pendingId}")->assertOk();
    expect(DB::table('teacher_availability')->find($pendingId))->toBeNull();

    $this->actingAs($teacher)
        ->deleteJson("/api/teacher/availability/{$approvedId}")
        ->assertUnprocessable();
    expect(DB::table('teacher_availability')->find($approvedId))->not->toBeNull();
});

it('forbids deleting another teachers availability', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);
    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

    $otherAvailabilityId = (string) Str::ulid();
    DB::table('teacher_availability')->insert([
        'id' => $otherAvailabilityId,
        'staff_profile_id' => $otherStaffProfileId,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'timezone' => 'UTC',
        'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved->value,
        'created_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->deleteJson("/api/teacher/availability/{$otherAvailabilityId}")
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/teacher/availability')->assertUnauthorized();
    $this->postJson('/api/teacher/availability', [])->assertUnauthorized();
    $this->deleteJson('/api/teacher/availability/'.(string) Str::ulid())->assertUnauthorized();
});
