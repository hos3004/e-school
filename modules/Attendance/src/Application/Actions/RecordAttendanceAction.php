<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceRecorded;
use Modules\Attendance\Domain\Models\Attendance;
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
    ) {}

    public function execute(
        string $sessionParticipantId,
        int $attendedMinutes,
        int $sessionMinutes,
        int $joinedAfterMinutes = 0,
        int $leftBeforeMinutes = 0,
    ): Attendance {
        $this->assertParticipantGiven($sessionParticipantId);
        $this->assertMinutesNonNegative($attendedMinutes, $joinedAfterMinutes, $leftBeforeMinutes);
        $this->assertSessionDurationPositive($sessionMinutes);

        $derivedStatus = AttendanceStatus::deriveFromMinutes(
            attendedMinutes: $attendedMinutes,
            sessionMinutes: $sessionMinutes,
            joinedAfterMinutes: $joinedAfterMinutes,
            leftBeforeMinutes: $leftBeforeMinutes,
        );

        $existing = Attendance::query()
            ->where('session_participant_id', $sessionParticipantId)
            ->first();

        $this->assertNotAlreadyRecorded($existing);

        $attendance = $this->transaction->run(function () use ($sessionParticipantId, $derivedStatus, $attendedMinutes, $joinedAfterMinutes, $leftBeforeMinutes): Attendance {
            return Attendance::query()->create([
                'session_participant_id' => $sessionParticipantId,
                'status' => $derivedStatus,
                'derived_status' => $derivedStatus,
                'attended_minutes' => $attendedMinutes,
                'joined_after_minutes' => $joinedAfterMinutes,
                'left_before_minutes' => $leftBeforeMinutes,
            ]);
        });

        $this->events->dispatch(new AttendanceRecorded(
            attendanceId: (string) $attendance->getKey(),
            sessionParticipantId: $sessionParticipantId,
            derivedStatus: $derivedStatus->value,
            attendedMinutes: $attendedMinutes,
        ));

        return $attendance;
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
