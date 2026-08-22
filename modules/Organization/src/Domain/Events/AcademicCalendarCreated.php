<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أُنشئ تقويم أكاديمي جديد.
 */
final class AcademicCalendarCreated extends DomainEvent
{
    public function __construct(
        public readonly string $academicCalendarId,
        public readonly string $organizationId,
        public readonly string $startsOn,
        public readonly string $endsOn,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.academic_calendar_created';
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
            'academic_calendar_id' => $this->academicCalendarId,
            'organization_id' => $this->organizationId,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
        ];
    }
}
