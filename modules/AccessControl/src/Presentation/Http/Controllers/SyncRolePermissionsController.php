<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Application\Actions\SyncRolePermissionsAction;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Http\Requests\SyncRolePermissionsRequest;
use Modules\AccessControl\Presentation\Http\Support\ActorOrganization;

final class SyncRolePermissionsController
{
    public function __construct(
        private readonly SyncRolePermissionsAction $action,
        private readonly AccessControlQuerier $querier,
    ) {}

    public function __invoke(SyncRolePermissionsRequest $request, string $roleId): JsonResponse
    {
        $validated = $request->validated();
        $organizationId = ActorOrganization::from($request);
        $role = Role::query()->forOrganization($organizationId)->findOrFail($roleId);
        Gate::authorize('syncPermissions', $role);

        $this->action->execute(
            roleId: $roleId,
            permissionNames: array_values((array) $validated['permissions']),
            actorId: (string) $request->user()?->getAuthIdentifier(),
            organizationId: $organizationId,
            reason: (string) $validated['reason'],
        );

        return response()->json([
            'data' => [
                'role_id' => $roleId,
                'permissions' => $this->querier->permissionNamesForRole($roleId),
            ],
        ]);
    }
}
