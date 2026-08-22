<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Modules\AccessControl\Application\Actions\UpdateRoleAction;
use Modules\AccessControl\Presentation\Http\Requests\UpdateRoleRequest;
use Modules\AccessControl\Presentation\Http\Resources\RoleResource;

final class UpdateRoleController
{
    public function __construct(
        private readonly UpdateRoleAction $action,
    ) {}

    public function __invoke(UpdateRoleRequest $request, string $roleId): RoleResource
    {
        $validated = $request->validated();

        $role = $this->action->execute(
            roleId: $roleId,
            name: $validated['name'] ?? null,
            organizationId: array_key_exists('organization_id', $validated) ? $validated['organization_id'] : null,
        );

        return new RoleResource($role);
    }
}
