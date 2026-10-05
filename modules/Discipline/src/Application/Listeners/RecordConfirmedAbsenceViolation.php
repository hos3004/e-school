<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Listeners;

use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;

/**
 * Converts only a teacher-confirmed unexcused absence into a discipline event.
 *
 * Provisional BBB attendance never reaches this listener. Excused, not-held,
 * technical, and present states are intentionally ignored.
 */
final readonly class RecordConfirmedAbsenceViolation
{
    public function __construct(
        private SessionParticipantAdministrationQueries $participants,
        private StaffQueries $staff,
        private RecordViolationAction $record,
    ) {}

    public function handle(AttendanceConfirmed $event): void
    {
        $status = AttendanceStatus::tryFrom($event->status);
        $type = match ($status) {
            AttendanceStatus::Absent => ViolationType::UnexcusedAbsence,
            AttendanceStatus::NoShow => ViolationType::NoShow,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $participant = $this->participants->find($event->sessionParticipantId);
        if ($participant === null || !$participant->invitationActive) {
            return;
        }

        $assignedTeacherUserId = $this->staff->userIdForProfile(
            $participant->organizationId,
            $participant->staffProfileId,
        );
        if ($assignedTeacherUserId === null || $event->confirmedBy !== $assignedTeacherUserId) {
            return;
        }

        $this->record->execute([
            'organization_id' => $participant->organizationId,
            'enrollment_id' => $participant->enrollmentId,
            'student_profile_id' => $participant->studentProfileId,
            'session_id' => $participant->sessionId,
            'source_event_id' => $event->eventId,
            'type' => $type,
            'occurred_at' => $event->occurredAt->toIso8601String(),
        ]);
    }
}
