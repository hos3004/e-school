<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

/** أُعيد فتح مستوى مُقفل بسبب موثّق. */
final class LevelReopened extends DomainEvent
{
    public function __construct(
        public readonly string $levelId,
        public readonly string $organizationId,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.level_reopened';
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
            'level_id' => $this->levelId,
            'organization_id' => $this->organizationId,
            'reason' => $this->reason,
        ];
    }
}
