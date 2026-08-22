<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

final class ScheduleCreated extends DomainEvent
{
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $staffProfileId,
        public readonly string $courseId,
        public readonly string $rrule,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.schedule_created';
    }

    public function module(): string
    {
        return 'Scheduling';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'staff_profile_id' => $this->staffProfileId,
            'course_id' => $this->courseId,
            'rrule' => $this->rrule,
        ];
    }
}
