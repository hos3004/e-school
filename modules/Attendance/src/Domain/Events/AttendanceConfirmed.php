<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * اعتمد المعلم حالة الحضور المشتقة — صارت نهائية للتقارير والمستحقات.
 */
final class AttendanceConfirmed extends DomainEvent
{
    public function __construct(
        public readonly string $attendanceId,
        public readonly string $sessionParticipantId,
        public readonly string $status,
        public readonly string $confirmedBy,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'attendance.confirmed';
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
            'status' => $this->status,
            'confirmed_by' => $this->confirmedBy,
        ];
    }
}
