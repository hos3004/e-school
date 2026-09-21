<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

/** أُعيد فتح كورس مُقفل بسبب موثّق. */
final class CourseReopened extends DomainEvent
{
    public function __construct(
        public readonly string $courseId,
        public readonly string $organizationId,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.course_reopened';
    }

    public function module(): string
    {
        return 'Academics';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'course_id' => $this->courseId,
            'organization_id' => $this->organizationId,
            'reason' => $this->reason,
        ];
    }
}
