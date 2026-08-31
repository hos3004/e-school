<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Services;

use Modules\AccessControl\Application\Actions\AssignRoleAction;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Shared\Support\BusinessRuleViolation;

final readonly class RoleAssignmentService implements RoleAssignmentGateway
{
    public function __construct(private AssignRoleAction $assignRole) {}

    public function assignIfMissing(
        string $roleName,
        string $modelType,
        string $modelId,
        string $organizationId,
        ?string $actorId = null,
    ): bool {
        /** @var Role|null $role */
        $role = Role::query()
            ->includingGlobal($organizationId)
            ->where('name', $roleName)
            ->orderByRaw('organization_id IS NULL')
            ->first();

        if ($role === null) {
            throw BusinessRuleViolation::make(
                'accesscontrol.role.not_found',
                'accesscontrol::errors.role_not_found',
            );
        }

        $alreadyAssigned = ModelHasRole::query()
            ->where('role_id', $role->getKey())
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->exists();

        if ($alreadyAssigned) {
            return false;
        }

        $this->assignRole->execute(
            roleId: (string) $role->getKey(),
            modelType: $modelType,
            modelId: $modelId,
            actorId: $actorId,
            organizationId: $organizationId,
        );

        return true;
    }
}
