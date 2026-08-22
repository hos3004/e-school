<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceOverridden;
use Modules\Attendance\Domain\Models\Attendance;
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
    ) {}

    public function execute(Attendance $attendance, AttendanceStatus $newStatus, string $reason): Attendance
    {
        $this->assertReasonSufficient($reason);
        $this->assertActualChange($attendance, $newStatus);

        /** @var AttendanceStatus $fromStatus */
        $fromStatus = $attendance->status;

        $this->transaction->run(function () use ($attendance, $newStatus, $reason): void {
            $attendance->forceFill([
                'status' => $newStatus,
                'override_reason' => trim($reason),
                'confirmed_at' => now()->utc(),
                'confirmed_by' => $attendance->confirmed_by ?? auth()->id(),
            ])->save();
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
