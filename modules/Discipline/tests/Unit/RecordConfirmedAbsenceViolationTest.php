<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Discipline\Application\Listeners\RecordConfirmedAbsenceViolation;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Discipline\Domain\Events\ViolationRecorded;
use Modules\Discipline\Domain\Models\ViolationEvent;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

function assignedTeacherUserId(string $participantId): string
{
    return (string) DB::table('session_participants')
        ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
        ->join('staff_profiles', 'staff_profiles.id', '=', 'sessions.staff_profile_id')
        ->where('session_participants.id', $participantId)
        ->value('staff_profiles.user_id');
}

it('records a confirmed no-show once even when the same event is delivered twice', function (): void {
    Event::fake([ViolationRecorded::class, DisciplineActionApplied::class]);

    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $event = new AttendanceConfirmed(
        attendanceId: (string) str()->ulid(),
        sessionParticipantId: $participantId,
        status: AttendanceStatus::NoShow->value,
        confirmedBy: assignedTeacherUserId($participantId),
    );

    $listener = app(RecordConfirmedAbsenceViolation::class);
    $listener->handle($event);
    $listener->handle($event);

    $violation = ViolationEvent::query()->sole();

    expect($violation->type->value)->toBe('no_show')
        ->and((string) $violation->source_event_id)->toBe($event->eventId)
        ->and((string) $violation->session_id)->not->toBe('');

    Event::assertDispatchedTimes(ViolationRecorded::class, 1);
});

it('records a confirmed unexcused absence', function (): void {
    Event::fake([ViolationRecorded::class, DisciplineActionApplied::class]);

    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound

    app(RecordConfirmedAbsenceViolation::class)->handle(new AttendanceConfirmed(
        attendanceId: (string) str()->ulid(),
        sessionParticipantId: $participantId,
        status: AttendanceStatus::Absent->value,
        confirmedBy: assignedTeacherUserId($participantId),
    ));

    expect(ViolationEvent::query()->sole()->type->value)->toBe('unexcused_absence');
});

it('does not penalize present excused or not-held attendance', function (AttendanceStatus $status): void {
    Event::fake([ViolationRecorded::class, DisciplineActionApplied::class]);

    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound

    app(RecordConfirmedAbsenceViolation::class)->handle(new AttendanceConfirmed(
        attendanceId: (string) str()->ulid(),
        sessionParticipantId: $participantId,
        status: $status->value,
        confirmedBy: (string) str()->ulid(),
    ));

    expect(ViolationEvent::query()->count())->toBe(0);
})->with([
    AttendanceStatus::Present,
    AttendanceStatus::Excused,
    AttendanceStatus::NotHeld,
    AttendanceStatus::TechnicalIssue,
]);

it('does not penalize the student when the assigned teacher was absent', function (): void {
    Event::fake([ViolationRecorded::class, DisciplineActionApplied::class]);

    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound

    app(RecordConfirmedAbsenceViolation::class)->handle(new AttendanceConfirmed(
        attendanceId: (string) str()->ulid(),
        sessionParticipantId: $participantId,
        status: AttendanceStatus::NoShow->value,
        confirmedBy: (string) str()->ulid(),
    ));

    expect(ViolationEvent::query()->count())->toBe(0);
    Event::assertNotDispatched(ViolationRecorded::class);
});
