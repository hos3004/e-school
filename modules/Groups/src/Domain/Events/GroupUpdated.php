<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Events;

use Shared\Domain\DomainEvent;

final class GroupUpdated extends DomainEvent
{
    /**
     * @param list<string> $updatedFields
     */
    public function __construct(
        public readonly string $groupId,
        public readonly string $organizationId,
        public readonly array $updatedFields,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'groups.updated';
    }

    public function module(): string
    {
        return 'Groups';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'group_id' => $this->groupId,
            'organization_id' => $this->organizationId,
            'updated_fields' => $this->updatedFields,
        ];
    }
}
