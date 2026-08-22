<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Events;

use Shared\Domain\DomainEvent;

final class RoleCreated extends DomainEvent
{
    public function __construct(
        public readonly string $roleId,
        public readonly ?string $organizationId,
        public readonly string $name,
        public readonly string $guardName,
        public readonly bool $isSystem,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'accesscontrol.role_created';
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
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'guard_name' => $this->guardName,
            'is_system' => $this->isSystem,
        ];
    }
}
