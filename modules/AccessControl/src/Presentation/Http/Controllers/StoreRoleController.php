<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Modules\AccessControl\Application\Actions\CreateRoleAction;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Presentation\Http\Requests\StoreRoleRequest;
use Modules\AccessControl\Presentation\Http\Resources\RoleResource;

final class StoreRoleController
{
    public function __construct(
        private readonly CreateRoleAction $action,
    ) {}

    public function __invoke(StoreRoleRequest $request): RoleResource
    {
        $validated = $request->validated();

        $role = $this->action->execute(
            name: (string) $validated['name'],
            guard: GuardName::from((string) ($validated['guard_name'] ?? 'web')),
            organizationId: $validated['organization_id'] ?? null,
        );

        return new RoleResource($role);
    }
}
