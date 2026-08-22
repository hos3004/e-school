<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Events;

use Shared\Domain\DomainEvent;

final class PermissionCreated extends DomainEvent
{
    public function __construct(
        public readonly string $permissionId,
        public readonly string $name,
        public readonly string $guardName,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'accesscontrol.permission_created';
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
            'permission_id' => $this->permissionId,
            'name' => $this->name,
            'guard_name' => $this->guardName,
        ];
    }
}
