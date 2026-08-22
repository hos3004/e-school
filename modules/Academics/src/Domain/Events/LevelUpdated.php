<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

final class LevelUpdated extends DomainEvent
{
    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public readonly string $levelId,
        public readonly string $programId,
        public readonly array $changedFields,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.level_updated';
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
            'changed_fields' => $this->changedFields,
        ];
    }
}
