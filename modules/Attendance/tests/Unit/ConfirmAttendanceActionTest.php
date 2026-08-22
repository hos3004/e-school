<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Attendance\Application\Actions\ConfirmAttendanceAction;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Attendance\Domain\Models\Attendance;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('academic.attendance.thresholds', [
        'partial_min_percent' => 25,
        'present_min_percent' => 75,
        'left_early_before_minutes' => 10,
        'late_after_minutes' => 5,
    ]);
});

function createUnconfirmedAttendance(): Attendance
{
    return app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: (string) str()->ulid(),
        attendedMinutes: 60,
        sessionMinutes: 60,
    );
}

it('confirms attendance and stamps the confirmer and time', function () {
    Event::fake([AttendanceConfirmed::class]);

    $attendance = createUnconfirmedAttendance();
    $confirmerId = (string) str()->ulid();

    app(ConfirmAttendanceAction::class)->execute($attendance, $confirmerId);

    $attendance->refresh();

    expect($attendance->isConfirmed())->toBeTrue()
        ->and($attendance->confirmed_by)->toBe($confirmerId)
        ->and($attendance->confirmed_at)->not->toBeNull();

    Event::assertDispatched(
        AttendanceConfirmed::class,
        fn (AttendanceConfirmed $event): bool => $event->attendanceId === (string) $attendance->getKey()
            && $event->confirmedBy === $confirmerId
            && $event->status === $attendance->status->value
    );
});

it('rejects confirming an already confirmed record', function () {
    $attendance = createUnconfirmedAttendance();
    $action = app(ConfirmAttendanceAction::class);

    $action->execute($attendance, (string) str()->ulid());
    $action->execute($attendance, (string) str()->ulid());
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects confirming without a confirmer identifier', function () {
    $attendance = createUnconfirmedAttendance();

    app(ConfirmAttendanceAction::class)->execute($attendance, ' ');
})->throws(Shared\Support\BusinessRuleViolation::class);
