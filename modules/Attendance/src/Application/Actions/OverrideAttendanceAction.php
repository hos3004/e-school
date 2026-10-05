<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceOverridden;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تجاوز حالة الحضور بحالة أخرى — قرار بشري موثّق بسبب مكتوب.
 *
 * قواعد العمل:
 *  - السبب إلزامي وبحد أدنى من config('attendance.override.reason_min_chars')
 *    (قاعدة التدقيق: لا تغيير على الحضور بدون سبب).
 *  - لا تجاوز بلا تغيير فعلي — نفس الحالة رفض.
 *  - التجاوز يختم القيد بالاعتماد (confirmed_by/at) لأنه قرار نهائي.
 */
final readonly class OverrideAttendanceAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private SessionParticipantAdministrationQueries $participants,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Attendance $attendance,
        AttendanceStatus $newStatus,
        string $reason,
        ?string $actorId = null,
        ?string $organizationId = null,
    ): Attendance {
        $this->assertReasonSufficient($reason);

        [$attendance, $fromStatus] = $this->transaction->run(function () use (
            $attendance,
            $newStatus,
            $reason,
            $actorId,
            $organizationId,
        ): array {
            /** @var Attendance $locked */
            $locked = Attendance::query()->lockForUpdate()->findOrFail((string) $attendance->getKey());
            $participant = $organizationId === null
                ? $this->participants->find((string) $locked->session_participant_id)
                : $this->participants->findForOrganization(
                    $organizationId,
                    (string) $locked->session_participant_id,
                );
            if ($participant === null || !$participant->invitationActive) {
                throw BusinessRuleViolation::make(
                    'attendance.participant_not_active',
                    'attendance::errors.participant_not_active',
                );
            }

            $this->assertActualChange($locked, $newStatus);
            /** @var AttendanceStatus $fromStatus */
            $fromStatus = $locked->status;
            $before = [
                'status' => $fromStatus->value,
                'derived_status' => $locked->derived_status->value,
                'confirmed_by' => $locked->confirmed_by,
                'confirmed_at' => $locked->confirmed_at?->toIso8601String(),
                'override_reason' => $locked->override_reason,
            ];

            $locked->forceFill([
                'status' => $newStatus,
                'override_reason' => trim($reason),
                'confirmed_at' => now()->utc(),
                'confirmed_by' => $locked->confirmed_by ?? $actorId,
            ])->save();

            $this->audit->record(
                organizationId: $participant->organizationId,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'attendance.overridden',
                auditableType: 'attendances',
                auditableId: (string) $locked->getKey(),
                oldValues: $before,
                newValues: [
                    'status' => $newStatus->value,
                    'derived_status' => $locked->derived_status->value,
                    'confirmed_by' => $locked->confirmed_by,
                    'confirmed_at' => $locked->confirmed_at?->toIso8601String(),
                    'override_reason' => trim($reason),
                ],
                reason: trim($reason),
            );

            return [$locked, $fromStatus];
        });

        $this->events->dispatch(new AttendanceOverridden(
            attendanceId: (string) $attendance->getKey(),
            sessionParticipantId: (string) $attendance->session_participant_id,
            fromStatus: $fromStatus->value,
            toStatus: $newStatus->value,
            reason: trim($reason),
        ));

        return $attendance;
    }

    private function assertReasonSufficient(string $reason): void
    {
        $min = max(1, (int) config('attendance.override.reason_min_chars', 5));

        if (mb_strlen(trim($reason)) < $min) {
            throw BusinessRuleViolation::make(
                'attendance.override_reason_required',
                'attendance::errors.override_reason_required',
                ['min_chars' => $min],
            );
        }
    }

    private function assertActualChange(Attendance $attendance, AttendanceStatus $newStatus): void
    {
        if ($attendance->status === $newStatus) {
            throw BusinessRuleViolation::make(
                'attendance.override_no_change',
                'attendance::errors.override_no_change',
                ['status' => $newStatus->value],
            );
        }
    }
}
