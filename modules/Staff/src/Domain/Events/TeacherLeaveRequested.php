<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Events;

use Shared\Domain\DomainEvent;

final class TeacherLeaveRequested extends DomainEvent
{
    public function __construct(
        public readonly string $leaveId,
        public readonly string $staffProfileId,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly ?string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'staff.leave_requested';
    }

    public function module(): string
    {
        return 'Staff';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'leave_id' => $this->leaveId,
            'staff_profile_id' => $this->staffProfileId,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'reason' => $this->reason,
        ];
    }
}
