<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceRecorded;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

beforeEach(function (): void {
    config()->set('academic.attendance.thresholds', [
        'partial_min_percent' => 25,
        'present_min_percent' => 75,
        'left_early_before_minutes' => 10,
        'late_after_minutes' => 5,
    ]);
});

it('records attendance with the derived status and publishes the event', function (): void {
    Event::fake([AttendanceRecorded::class]);

    $participantId = $this->createSessionParticipant();

    $attendance = app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: $participantId,
        attendedMinutes: 60,
        sessionMinutes: 60,
        joinedAfterMinutes: 2,
        leftBeforeMinutes: 0,
    );

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->derived_status)->toBe(AttendanceStatus::Present)
        ->and($attendance->isConfirmed())->toBeFalse();

    expect(
        Attendance::query()->where('session_participant_id', $participantId)->exists(),
    )->toBeTrue();

    Event::assertDispatched(
        AttendanceRecorded::class,
        fn (AttendanceRecorded $event): bool => $event->sessionParticipantId === $participantId
            && $event->derivedStatus === AttendanceStatus::Present->value
            && $event->attendedMinutes === 60,
    );
});

it('rejects recording attendance twice for the same participant', function (): void {
    $action = app(RecordAttendanceAction::class);
    $participantId = $this->createSessionParticipant();

    $action->execute($participantId, 50, 60);

    $action->execute($participantId, 40, 60);
})->throws(BusinessRuleViolation::class);

it('rejects negative minutes', function (): void {
    app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: (string) str()->ulid(),
        attendedMinutes: -5,
        sessionMinutes: 60,
    );
})->throws(BusinessRuleViolation::class);

it('rejects a non-positive session duration', function (): void {
    app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: (string) str()->ulid(),
        attendedMinutes: 30,
        sessionMinutes: 0,
    );
})->throws(BusinessRuleViolation::class);

it('rejects an empty participant identifier', function (): void {
    app(RecordAttendanceAction::class)->execute('   ', 30, 60);
})->throws(BusinessRuleViolation::class);
