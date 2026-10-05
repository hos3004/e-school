<?php

declare(strict_types=1);

use App\Listeners\FinalizeClassroomAttendance;
use App\Listeners\TrackClassroomParticipantAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantLeft;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

/** @return array{participant_id: string, session_id: string, user_id: string, start: CarbonImmutable, end: CarbonImmutable} */
function classroomEventContext(string $participantId): array
{
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $session = DB::table('sessions')->where('id', $participant->session_id)->first();
    $student = DB::table('student_profiles')->where('id', $participant->student_profile_id)->first();

    return [
        'participant_id' => $participantId,
        'session_id' => (string) $session->id,
        'user_id' => (string) $student->user_id,
        'start' => CarbonImmutable::parse((string) $session->scheduled_start, 'UTC'),
        'end' => CarbonImmutable::parse((string) $session->scheduled_end, 'UTC'),
    ];
}

/** @param array{participant_id: string, session_id: string, user_id: string, start: CarbonImmutable, end: CarbonImmutable} $context */
function joinedEvent(array $context, CarbonImmutable $at): ClassroomParticipantJoined
{
    return new ClassroomParticipantJoined(
        classroomId: (string) str()->ulid(),
        sessionId: $context['session_id'],
        provider: 'bigbluebutton',
        externalUserId: $context['user_id'],
        userId: $context['user_id'],
        role: JoinRole::Viewer,
        occurredAt: $at->toIso8601String(),
    );
}

/** @param array{participant_id: string, session_id: string, user_id: string, start: CarbonImmutable, end: CarbonImmutable} $context */
function leftEvent(array $context, CarbonImmutable $at): ClassroomParticipantLeft
{
    return new ClassroomParticipantLeft(
        classroomId: (string) str()->ulid(),
        sessionId: $context['session_id'],
        provider: 'bigbluebutton',
        externalUserId: $context['user_id'],
        userId: $context['user_id'],
        occurredAt: $at->toIso8601String(),
    );
}

/** @param array{participant_id: string, session_id: string, user_id: string, start: CarbonImmutable, end: CarbonImmutable} $context */
function endedEvent(array $context, CarbonImmutable $at): ClassroomEnded
{
    return new ClassroomEnded(
        classroomId: (string) str()->ulid(),
        sessionId: $context['session_id'],
        provider: 'bigbluebutton',
        endedAt: $at->toIso8601String(),
        maxConcurrentParticipants: 2,
    );
}

it('counts only the official session interval although the room stays open later', function (): void {
    $context = classroomEventContext(
        $this->createSessionParticipant(), // @phpstan-ignore method.notFound
    );

    app(TrackClassroomParticipantAttendance::class)->handle(joinedEvent($context, $context['start']->subMinutes(10)));
    app(TrackClassroomParticipantAttendance::class)->handle(leftEvent($context, $context['end']->addMinutes(10)));
    app(FinalizeClassroomAttendance::class)->handle(endedEvent($context, $context['end']->addMinutes(15)));

    $participant = DB::table('session_participants')->where('id', $context['participant_id'])->first();
    $attendance = Attendance::query()->sole();

    expect((int) $participant->attended_minutes)->toBe(60)
        ->and($attendance->attended_minutes)->toBe(60)
        ->and($attendance->derived_status)->toBe(AttendanceStatus::Present)
        ->and((int) config('virtual-classroom.join_window.after_minutes'))->toBe(15);
});

it('sums reconnect intervals before deriving the configured attendance threshold', function (): void {
    $context = classroomEventContext(
        $this->createSessionParticipant(), // @phpstan-ignore method.notFound
    );

    app(TrackClassroomParticipantAttendance::class)->handle(joinedEvent($context, $context['start']));
    app(TrackClassroomParticipantAttendance::class)->handle(leftEvent($context, $context['start']->addMinutes(20)));
    app(TrackClassroomParticipantAttendance::class)->handle(joinedEvent($context, $context['start']->addMinutes(35)));
    app(TrackClassroomParticipantAttendance::class)->handle(leftEvent($context, $context['end']));
    app(FinalizeClassroomAttendance::class)->handle(endedEvent($context, $context['end']->addMinutes(15)));

    $attendance = Attendance::query()->sole();

    expect($attendance->attended_minutes)->toBe(45)
        ->and($attendance->derived_status)->toBe(AttendanceStatus::Present)
        ->and(config('academic.attendance.thresholds.present_min_percent'))->toBe(75)
        ->and(config('academic.attendance.thresholds.partial_min_percent'))->toBe(40);
});

it('classifies exactly forty percent of the official duration as partial', function (): void {
    $context = classroomEventContext(
        $this->createSessionParticipant(), // @phpstan-ignore method.notFound
    );

    app(TrackClassroomParticipantAttendance::class)->handle(joinedEvent($context, $context['start']));
    app(TrackClassroomParticipantAttendance::class)->handle(
        leftEvent($context, $context['start']->addMinutes(24)),
    );
    app(FinalizeClassroomAttendance::class)->handle(endedEvent($context, $context['end']->addMinutes(15)));

    $attendance = Attendance::query()->sole();

    expect($attendance->attended_minutes)->toBe(24)
        ->and($attendance->derived_status)->toBe(AttendanceStatus::Partial);
});

it('does not grant attendance to a student who joins after the official end', function (): void {
    $context = classroomEventContext(
        $this->createSessionParticipant(), // @phpstan-ignore method.notFound
    );

    app(TrackClassroomParticipantAttendance::class)->handle(joinedEvent($context, $context['end']->addMinutes(5)));
    app(TrackClassroomParticipantAttendance::class)->handle(leftEvent($context, $context['end']->addMinutes(10)));
    app(FinalizeClassroomAttendance::class)->handle(endedEvent($context, $context['end']->addMinutes(15)));

    $attendance = Attendance::query()->sole();

    expect($attendance->attended_minutes)->toBe(0)
        ->and($attendance->derived_status)->toBe(AttendanceStatus::NoShow);
});
