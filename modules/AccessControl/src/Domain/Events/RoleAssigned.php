<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Events;

use Shared\Domain\DomainEvent;

final class RoleAssigned extends DomainEvent
{
    public function __construct(
        public readonly string $roleId,
        public readonly string $modelName,
        public readonly string $modelId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'accesscontrol.role_assigned';
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
            'model_type' => $this->modelName,
            'model_id' => $this->modelId,
        ];
    }
}
