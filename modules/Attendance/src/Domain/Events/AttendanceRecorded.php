<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * رُصد حضور طالب لحصة — الحالة المشتقة اقتراح بانتظار اعتماد المعلم.
 *
 * الحمولة معرّفات وقيَم بدائية فقط؛ الحالة تُمرَّر كنص قيمة الـ enum.
 */
final class AttendanceRecorded extends DomainEvent
{
    public function __construct(
        public readonly string $attendanceId,
        public readonly string $sessionParticipantId,
        public readonly string $derivedStatus,
        public readonly int $attendedMinutes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'attendance.recorded';
    }

    public function module(): string
    {
        return 'Attendance';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'attendance_id' => $this->attendanceId,
            'session_participant_id' => $this->sessionParticipantId,
            'derived_status' => $this->derivedStatus,
            'attended_minutes' => $this->attendedMinutes,
        ];
    }
}
