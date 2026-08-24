<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Application\Actions\DeleteRoleAction;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Http\Support\ActorOrganization;

final class DeleteRoleController
{
    public function __construct(
        private readonly DeleteRoleAction $action,
    ) {}

    public function __invoke(Request $request, string $roleId): JsonResponse
    {
        $organizationId = ActorOrganization::from($request);
        $role = Role::query()->forOrganization($organizationId)->findOrFail($roleId);
        Gate::authorize('delete', $role);

        $this->action->execute(
            roleId: $roleId,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            organizationId: $organizationId,
        );

        return response()->json(status: 204);
    }
}
