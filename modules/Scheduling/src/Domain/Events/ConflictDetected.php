<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

final class ConflictDetected extends DomainEvent
{
    /**
     * @param list<string> $conflictingSessionIds
     */
    public function __construct(
        public readonly string $type,
        public readonly string $entityId,
        public readonly array $conflictingSessionIds,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.conflict_detected';
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
            'type' => $this->type,
            'entity_id' => $this->entityId,
            'conflicting_session_ids' => $this->conflictingSessionIds,
        ];
    }
}
