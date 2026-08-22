<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * نُشّط تقويم أكاديمي — صار مرجع الجدولة للمؤسسة.
 */
final class AcademicCalendarActivated extends DomainEvent
{
    public function __construct(
        public readonly string $academicCalendarId,
        public readonly string $organizationId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'organization.academic_calendar_activated';
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
        ];
    }
}
