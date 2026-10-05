<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceRecorded;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * رصد حضور طالب في حصة.
 *
 * الحالة تُستنبط آليًا من الدقائق الفعلية عبر AttendanceStatus::deriveFromMinutes
 * وتُخزَّن كاقتراح (status = derived_status) بانتظار اعتماد المعلم.
 *
 * الترتيب إلزامي: حراس ← معاملة قاعدة البيانات ← نشر الأحداث بعد النجاح.
 */
final readonly class RecordAttendanceAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private SessionParticipantAdministrationQueries $participants,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $sessionParticipantId,
        int $attendedMinutes,
        int $sessionMinutes,
        int $joinedAfterMinutes = 0,
        int $leftBeforeMinutes = 0,
        ?string $organizationId = null,
        ?string $actorId = null,
        ?AttendanceStatus $forcedStatus = null,
        ?string $reason = null,
    ): Attendance {
        $this->assertParticipantGiven($sessionParticipantId);
        $this->assertMinutesNonNegative($attendedMinutes, $joinedAfterMinutes, $leftBeforeMinutes);
        $this->assertSessionDurationPositive($sessionMinutes);
        $participant = $this->resolveActiveParticipant($sessionParticipantId, $organizationId);
        $reason = trim($reason ?? (string) __('attendance::messages.record_reason'));

        $derivedStatus = $forcedStatus ?? AttendanceStatus::deriveFromMinutes(
            attendedMinutes: $attendedMinutes,
            sessionMinutes: $sessionMinutes,
            joinedAfterMinutes: $joinedAfterMinutes,
            leftBeforeMinutes: $leftBeforeMinutes,
        );

        $existing = Attendance::query()
            ->where('session_participant_id', $sessionParticipantId)
            ->first();

        $this->assertNotAlreadyRecorded($existing);

        $attendance = $this->transaction->run(function () use (
            $participant,
            $derivedStatus,
            $attendedMinutes,
            $joinedAfterMinutes,
            $leftBeforeMinutes,
            $actorId,
            $reason,
        ): Attendance {
            $attendance = Attendance::query()->create([
                'session_participant_id' => $participant->id,
                'status' => $derivedStatus,
                'derived_status' => $derivedStatus,
                'attended_minutes' => $attendedMinutes,
                'joined_after_minutes' => $joinedAfterMinutes,
                'left_before_minutes' => $leftBeforeMinutes,
            ]);

            $this->audit->record(
                organizationId: $participant->organizationId,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'attendance.recorded',
                auditableType: 'attendances',
                auditableId: (string) $attendance->getKey(),
                oldValues: null,
                newValues: [
                    'session_participant_id' => $participant->id,
                    'status' => $derivedStatus->value,
                    'attended_minutes' => $attendedMinutes,
                    'joined_after_minutes' => $joinedAfterMinutes,
                    'left_before_minutes' => $leftBeforeMinutes,
                ],
                reason: $reason,
            );

            return $attendance;
        });

        $this->events->dispatch(new AttendanceRecorded(
            attendanceId: (string) $attendance->getKey(),
            sessionParticipantId: $sessionParticipantId,
            derivedStatus: $derivedStatus->value,
            attendedMinutes: $attendedMinutes,
        ));

        return $attendance;
    }

    private function resolveActiveParticipant(
        string $participantId,
        ?string $organizationId,
    ): SessionParticipantAdministrationData {
        $participant = $organizationId === null
            ? $this->participants->find($participantId)
            : $this->participants->findForOrganization($organizationId, $participantId);

        if ($participant === null || !$participant->invitationActive) {
            throw BusinessRuleViolation::make(
                'attendance.participant_not_active',
                'attendance::errors.participant_not_active',
            );
        }

        return $participant;
    }

    private function assertParticipantGiven(string $sessionParticipantId): void
    {
        if (trim($sessionParticipantId) === '') {
            throw BusinessRuleViolation::make(
                'attendance.participant_required',
                'attendance::errors.participant_required',
            );
        }
    }

    private function assertNotAlreadyRecorded(?Attendance $existing): void
    {
        if ($existing !== null) {
            throw BusinessRuleViolation::make(
                'attendance.already_recorded',
                'attendance::errors.already_recorded',
                ['participant_id' => $existing->session_participant_id],
            );
        }
    }

    private function assertMinutesNonNegative(int ...$minutes): void
    {
        foreach ($minutes as $value) {
            if ($value < 0) {
                throw BusinessRuleViolation::make(
                    'attendance.negative_minutes',
                    'attendance::errors.negative_minutes',
                    ['minutes' => $value],
                );
            }
        }
    }

    private function assertSessionDurationPositive(int $sessionMinutes): void
    {
        if ($sessionMinutes <= 0) {
            throw BusinessRuleViolation::make(
                'attendance.invalid_session_duration',
                'attendance::errors.invalid_session_duration',
                ['session_minutes' => $sessionMinutes],
            );
        }
    }
}
