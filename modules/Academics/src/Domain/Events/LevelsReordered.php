<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

final class LevelsReordered extends DomainEvent
{
    /**
     * @param array<string, int> $ordering level_id => sort_order الجديد
     */
    public function __construct(
        public readonly string $programId,
        public readonly array $ordering,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.levels_reordered';
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
            'program_id' => $this->programId,
            'ordering' => $this->ordering,
        ];
    }
}
