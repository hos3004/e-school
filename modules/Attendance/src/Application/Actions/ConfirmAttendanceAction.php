<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * اعتماد حالة الحضور المشتقة — تحوّل الاقتراح إلى قرار نهائي.
 *
 * الاعتماد لا يغيّر الحالة؛ يختمها باسم المعلم ووقت الاعتماد.
 * تغيير الحالة نفسه مسار منفصل عبر OverrideAttendanceAction بسبب مكتوب.
 */
final readonly class ConfirmAttendanceAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private SessionParticipantAdministrationQueries $participants,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Attendance $attendance,
        string $confirmedBy,
        ?string $reason = null,
        ?string $organizationId = null,
    ): Attendance {
        $this->assertConfirmerGiven($confirmedBy);
        $reason = trim($reason ?? (string) __('attendance::messages.confirm_reason'));

        $attendance = $this->transaction->run(function () use (
            $attendance,
            $confirmedBy,
            $reason,
            $organizationId,
        ): Attendance {
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

            $this->assertNotAlreadyConfirmed($locked);
            $locked->forceFill([
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now()->utc(),
            ])->save();

            $this->audit->record(
                organizationId: $participant->organizationId,
                actorId: $confirmedBy,
                actorType: 'user',
                action: 'attendance.confirmed',
                auditableType: 'attendances',
                auditableId: (string) $locked->getKey(),
                oldValues: ['confirmed_by' => null, 'confirmed_at' => null],
                newValues: [
                    'status' => $locked->status->value,
                    'confirmed_by' => $confirmedBy,
                    'confirmed_at' => $locked->confirmed_at?->toIso8601String(),
                ],
                reason: $reason,
            );

            return $locked;
        });

        /** @var AttendanceStatus $status */
        $status = $attendance->status;

        $this->events->dispatch(new AttendanceConfirmed(
            attendanceId: (string) $attendance->getKey(),
            sessionParticipantId: (string) $attendance->session_participant_id,
            status: $status->value,
            confirmedBy: $confirmedBy,
        ));

        return $attendance;
    }

    private function assertConfirmerGiven(string $confirmedBy): void
    {
        if (trim($confirmedBy) === '') {
            throw BusinessRuleViolation::make(
                'attendance.confirmer_required',
                'attendance::errors.confirmer_required',
            );
        }
    }

    private function assertNotAlreadyConfirmed(Attendance $attendance): void
    {
        if ($attendance->isConfirmed()) {
            throw BusinessRuleViolation::make(
                'attendance.already_confirmed',
                'attendance::errors.already_confirmed',
                ['attendance_id' => (string) $attendance->getKey()],
            );
        }
    }
}
