<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Events;

use Shared\Domain\DomainEvent;

final class GroupActivated extends DomainEvent
{
    public function __construct(
        public readonly string $groupId,
        public readonly string $organizationId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'groups.activated';
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
        ];
    }
}
