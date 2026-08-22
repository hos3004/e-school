<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Attendance\Application\Actions\ConfirmAttendanceAction;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

beforeEach(function (): void {
    config()->set('academic.attendance.thresholds', [
        'partial_min_percent' => 25,
        'present_min_percent' => 75,
        'left_early_before_minutes' => 10,
        'late_after_minutes' => 5,
    ]);
});

function createUnconfirmedAttendance(string $participantId): Attendance
{
    return app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: $participantId,
        attendedMinutes: 60,
        sessionMinutes: 60,
    );
}

it('confirms attendance and stamps the confirmer and time', function (): void {
    Event::fake([AttendanceConfirmed::class]);

    $attendance = createUnconfirmedAttendance($this->createSessionParticipant());
    $confirmerId = Fixtures::userId();

    app(ConfirmAttendanceAction::class)->execute($attendance, $confirmerId);

    $attendance->refresh();

    expect($attendance->isConfirmed())->toBeTrue()
        ->and($attendance->confirmed_by)->toBe($confirmerId)
        ->and($attendance->confirmed_at)->not->toBeNull();

    Event::assertDispatched(
        AttendanceConfirmed::class,
        fn (AttendanceConfirmed $event): bool => $event->attendanceId === (string) $attendance->getKey()
            && $event->confirmedBy === $confirmerId
            && $event->status === $attendance->status->value,
    );
});

it('rejects confirming an already confirmed record', function (): void {
    $attendance = createUnconfirmedAttendance($this->createSessionParticipant());
    $action = app(ConfirmAttendanceAction::class);

    $action->execute($attendance, Fixtures::userId());
    $action->execute($attendance, Fixtures::userId());
})->throws(BusinessRuleViolation::class);

it('rejects confirming without a confirmer identifier', function (): void {
    $attendance = createUnconfirmedAttendance($this->createSessionParticipant());

    app(ConfirmAttendanceAction::class)->execute($attendance, ' ');
})->throws(BusinessRuleViolation::class);
