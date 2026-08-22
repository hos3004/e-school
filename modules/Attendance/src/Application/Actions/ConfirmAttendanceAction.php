<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Attendance\Domain\Models\Attendance;
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
    ) {}

    public function execute(Attendance $attendance, string $confirmedBy): Attendance
    {
        $this->assertConfirmerGiven($confirmedBy);
        $this->assertNotAlreadyConfirmed($attendance);

        $this->transaction->run(function () use ($attendance, $confirmedBy): void {
            $attendance->forceFill([
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now()->utc(),
            ])->save();
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
