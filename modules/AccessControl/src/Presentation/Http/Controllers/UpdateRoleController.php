<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Application\Actions\UpdateRoleAction;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Http\Requests\UpdateRoleRequest;
use Modules\AccessControl\Presentation\Http\Resources\RoleResource;
use Modules\AccessControl\Presentation\Http\Support\ActorOrganization;

final class UpdateRoleController
{
    public function __construct(
        private readonly UpdateRoleAction $action,
    ) {}

    public function __invoke(UpdateRoleRequest $request, string $roleId): RoleResource
    {
        $validated = $request->validated();
        $organizationId = ActorOrganization::from($request);
        $target = Role::query()->forOrganization($organizationId)->findOrFail($roleId);
        Gate::authorize('update', $target);

        $role = $this->action->execute(
            roleId: $roleId,
            name: $validated['name'] ?? null,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            scopeOrganizationId: $organizationId,
            reason: (string) $validated['reason'],
        );

        return new RoleResource($role);
    }
}
