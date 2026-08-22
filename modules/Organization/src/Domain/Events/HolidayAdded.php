<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أُضيفت عطلة إلى تقويم المؤسسة.
 */
final class HolidayAdded extends DomainEvent
{
    public function __construct(
        public readonly string $holidayId,
        public readonly string $organizationId,
        public readonly ?string $academicCalendarId,
        public readonly string $startsOn,
        public readonly string $endsOn,
        public readonly bool $blocksScheduling,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.holiday_added';
    }

    public function module(): string
    {
        return 'organization';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'holiday_id' => $this->holidayId,
            'organization_id' => $this->organizationId,
            'academic_calendar_id' => $this->academicCalendarId,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'blocks_scheduling' => $this->blocksScheduling,
        ];
    }
}
