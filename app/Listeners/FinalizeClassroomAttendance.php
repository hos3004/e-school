<?php

declare(strict_types=1);

namespace App\Listeners;

use Carbon\CarbonImmutable;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAttendanceGateway;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Shared\Support\BusinessRuleViolation;

final readonly class FinalizeClassroomAttendance
{
    public function __construct(
        private SessionParticipantAttendanceGateway $gateway,
        private SessionAdministrationQueries $sessions,
        private SessionParticipantAdministrationQueries $participants,
        private RecordAttendanceAction $record,
    ) {}

    public function handle(ClassroomEnded $event): void
    {
        $endedAt = CarbonImmutable::parse($event->endedAt, 'UTC');
        $this->gateway->closeOpenIntervals($event->sessionId, $endedAt);

        $organizationId = $this->sessions->organizationIdForSession($event->sessionId);
        if ($organizationId === null) {
            return;
        }

        foreach ($this->participants->forSession($organizationId, $event->sessionId) as $participant) {
            if (!$participant->invitationActive) {
                continue;
            }

            $start = CarbonImmutable::parse($participant->scheduledStart, 'UTC');
            $end = CarbonImmutable::parse($participant->scheduledEnd, 'UTC');
            $sessionSeconds = max(1, (int) $start->diffInSeconds($end));
            $sessionMinutes = max(1, (int) ceil($sessionSeconds / 60));

            $joinedAfterMinutes = 0;
            if ($participant->firstJoinedAt !== null) {
                $firstJoinedAt = CarbonImmutable::parse($participant->firstJoinedAt, 'UTC');
                $joinedAfterMinutes = intdiv(
                    max(0, (int) $start->diffInSeconds($firstJoinedAt, false)),
                    60,
                );
            }

            $leftBeforeMinutes = 0;
            if ($participant->lastLeftAt !== null) {
                $lastLeftAt = CarbonImmutable::parse($participant->lastLeftAt, 'UTC');
                $leftBeforeMinutes = intdiv(
                    max(0, (int) $lastLeftAt->diffInSeconds($end, false)),
                    60,
                );
            }

            try {
                $this->record->execute(
                    sessionParticipantId: $participant->id,
                    attendedMinutes: min($participant->attendedMinutes, $sessionMinutes),
                    sessionMinutes: $sessionMinutes,
                    joinedAfterMinutes: $joinedAfterMinutes,
                    leftBeforeMinutes: $leftBeforeMinutes,
                    organizationId: $organizationId,
                    actorId: null,
                    reason: $participant->excusedAt === null ? null : __('sessions::messages.student_apology_attendance_reason'),
                    forcedStatus: $participant->excusedAt === null ? null : AttendanceStatus::Excused,
                );
            } catch (BusinessRuleViolation $violation) {
                if ($violation->rule !== 'attendance.already_recorded') {
                    throw $violation;
                }
            }
        }
    }
}
