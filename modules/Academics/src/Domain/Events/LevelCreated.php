<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

final class LevelCreated extends DomainEvent
{
    /**
     * @param array<string, string> $name
     */
    public function __construct(
        public readonly string $levelId,
        public readonly string $programId,
        public readonly string $code,
        public readonly array $name,
        public readonly int $sortOrder,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.level_created';
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
            'program_id' => $this->programId,
            'code' => $this->code,
            'name' => $this->name,
            'sort_order' => $this->sortOrder,
        ];
    }
}
