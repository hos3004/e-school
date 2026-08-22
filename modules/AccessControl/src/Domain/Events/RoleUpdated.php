<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Events;

use Shared\Domain\DomainEvent;

final class RoleUpdated extends DomainEvent
{
    /**
     * @param array<string, mixed> $changed
     */
    public function __construct(
        public readonly string $roleId,
        public readonly string $name,
        public readonly array $changed,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'accesscontrol.role_updated';
    }

    public function module(): string
    {
        return 'AccessControl';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'role_id' => $this->roleId,
            'name' => $this->name,
            'changed_fields' => array_keys($this->changed),
        ];
    }
}
