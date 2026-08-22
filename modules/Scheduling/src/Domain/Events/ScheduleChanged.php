<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

final class ScheduleChanged extends DomainEvent
{
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $effectiveFrom,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.schedule_changed';
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
            'effective_from' => $this->effectiveFrom,
        ];
    }
}
