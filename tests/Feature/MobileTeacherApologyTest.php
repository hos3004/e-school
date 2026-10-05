<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    Gate::define('attendance.record', fn (): bool => true);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('lets the assigned teacher submit and auto-approve an apology with enough notice', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addHours(3)->addMinutes(30),
    ]);

    $response = $this->actingAs($teacher)->postJson(
        "/api/teacher/sessions/{$session->id}/apology",
        ['reason' => 'ظرف صحي طارئ'],
    );

    $response->assertCreated()->assertJsonPath('status', 'submitted');

    $apology = TeacherApology::query()->where('session_id', $session->id)->firstOrFail();
    expect($apology->status)->toBe(ApologyStatus::Approved)
        ->and($apology->staff_profile_id)->toBe($staffProfileId);
});

it('rejects an apology submitted too close to the session start', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(10),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(40),
    ]);

    $this->actingAs($teacher)
        ->postJson("/api/teacher/sessions/{$session->id}/apology", ['reason' => 'محاولة متأخرة'])
        ->assertUnprocessable();
});

it('rejects an empty reason', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addHours(3)->addMinutes(30),
    ]);

    $this->actingAs($teacher)
        ->postJson("/api/teacher/sessions/{$session->id}/apology", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('forbids apologizing for a session assigned to a different teacher', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);
    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

    $session = Session::factory()->create([
        'staff_profile_id' => $otherStaffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addHours(3)->addMinutes(30),
    ]);

    $this->actingAs($teacher)
        ->postJson("/api/teacher/sessions/{$session->id}/apology", ['reason' => 'مش حصتي'])
        ->assertUnprocessable();
});

it('requires authentication', function (): void {
    $organizationId = Fixtures::organizationId();
    $staffProfileId = Fixtures::staffProfileId();

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addHours(3)->addMinutes(30),
    ]);

    $this->postJson("/api/teacher/sessions/{$session->id}/apology")->assertUnauthorized();
});
