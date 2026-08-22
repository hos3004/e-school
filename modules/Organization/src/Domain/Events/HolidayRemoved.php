<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أُزيلت عطلة من تقويم المؤسسة.
 */
final class HolidayRemoved extends DomainEvent
{
    public function __construct(
        public readonly string $holidayId,
        public readonly string $organizationId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.holiday_removed';
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
        ];
    }
}
