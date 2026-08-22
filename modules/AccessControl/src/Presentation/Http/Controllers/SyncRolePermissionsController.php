<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\SyncRolePermissionsAction;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Presentation\Http\Requests\SyncRolePermissionsRequest;

final class SyncRolePermissionsController
{
    public function __construct(
        private readonly SyncRolePermissionsAction $action,
        private readonly AccessControlQuerier $querier,
    ) {}

    public function __invoke(SyncRolePermissionsRequest $request, string $roleId): JsonResponse
    {
        $validated = $request->validated();

        $this->action->execute($roleId, array_values((array) $validated['permissions']));

        return response()->json([
            'data' => [
                'role_id' => $roleId,
                'permissions' => $this->querier->permissionNamesForRole($roleId),
            ],
        ]);
    }
}
