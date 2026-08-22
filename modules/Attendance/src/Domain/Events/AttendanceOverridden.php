<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * تجاوزت الإدارة/المعلم حالة الاستنباط بحالة أخرى — بسبب مكتوب إلزامي.
 *
 * مستمع التدقيق في موديول Audit يوثّق (من، قبل، بعد، السبب) من هذه الحمولة.
 */
final class AttendanceOverridden extends DomainEvent
{
    public function __construct(
        public readonly string $attendanceId,
        public readonly string $sessionParticipantId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'attendance.overridden';
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
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'reason' => $this->reason,
        ];
    }
}
