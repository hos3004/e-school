<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Events;

use Shared\Domain\DomainEvent;

final class RolePermissionsSynced extends DomainEvent
{
    /**
     * @param  list<string>  $permissionIds
     * @param  list<string>  $attached
     * @param  list<string>  $detached
     */
    public function __construct(
        public readonly string $roleId,
        public readonly array $permissionIds,
        public readonly array $attached,
        public readonly array $detached,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'accesscontrol.role_permissions_synced';
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
            'attached_count' => count($this->attached),
            'detached_count' => count($this->detached),
        ];
    }
}
