<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * عُفو عن مخالفة بقرار إداري — لا حذف للحدث أبدًا، فقط تعليم بالعفو وسببه.
 */
final class ViolationWaived extends DomainEvent
{
    public function __construct(
        public readonly string $violationId,
        public readonly string $organizationId,
        public readonly string $enrollmentId,
        public readonly int $countInWindowAfterWaiver,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'discipline.violation_waived';
    }

    public function module(): string
    {
        return 'Discipline';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'violation_id' => $this->violationId,
            'organization_id' => $this->organizationId,
            'enrollment_id' => $this->enrollmentId,
            'count_in_window_after_waiver' => $this->countInWindowAfterWaiver,
        ];
    }
}
