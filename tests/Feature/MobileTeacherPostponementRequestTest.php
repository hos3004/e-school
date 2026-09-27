<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    Gate::define('session.postpone.request', fn (): bool => true);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('lets the teacher request and immediately have their own postponement approved', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addDays(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDays(3)->addMinutes(30),
    ]);

    $response = $this->actingAs($teacher)->postJson(
        "/api/teacher/sessions/{$session->id}/postponement-requests",
        [
            'proposed_start' => CarbonImmutable::now('UTC')->addDays(5)->toIso8601String(),
            'reason' => 'ظرف طارئ عند المعلم',
        ],
    );

    $response->assertCreated()->assertJsonPath('status', 'approved');

    $record = PostponementRequest::query()->where('session_id', $session->id)->firstOrFail();
    expect($record->status->value)->toBe('scheduled')
        ->and($record->requested_by)->toBe($teacher->id);
});

it('rejects a proposed start in the past', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addDays(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDays(3)->addMinutes(30),
    ]);

    $this->actingAs($teacher)
        ->postJson("/api/teacher/sessions/{$session->id}/postponement-requests", [
            'proposed_start' => CarbonImmutable::now('UTC')->subDay()->toIso8601String(),
            'reason' => 'محاولة موعد ماضٍ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['proposed_start']);
});

it('forbids requesting a postponement for a session assigned to a different teacher', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);
    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

    $session = Session::factory()->create([
        'staff_profile_id' => $otherStaffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addDays(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDays(3)->addMinutes(30),
    ]);

    $this->actingAs($teacher)
        ->postJson("/api/teacher/sessions/{$session->id}/postponement-requests", [
            'proposed_start' => CarbonImmutable::now('UTC')->addDays(5)->toIso8601String(),
            'reason' => 'محاولة تأجيل حصة معلم آخر',
        ])
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
        'scheduled_start' => CarbonImmutable::now('UTC')->addDays(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDays(3)->addMinutes(30),
    ]);

    $this->postJson("/api/teacher/sessions/{$session->id}/postponement-requests")
        ->assertUnauthorized();
});
