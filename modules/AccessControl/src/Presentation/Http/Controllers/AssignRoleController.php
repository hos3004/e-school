<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Application\Actions\AssignRoleAction;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentTargetScope;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Http\Requests\AssignRoleRequest;
use Modules\AccessControl\Presentation\Http\Support\ActorOrganization;

final class AssignRoleController
{
    public function __construct(
        private readonly AssignRoleAction $action,
        private readonly RoleAssignmentTargetScope $targets,
    ) {}

    public function __invoke(AssignRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $organizationId = ActorOrganization::from($request);
        $roleId = (string) $validated['role_id'];
        $modelId = (string) $validated['model_id'];
        $role = Role::query()->includingGlobal($organizationId)->findOrFail($roleId);
        Gate::authorize('assign', $role);

        $this->action->execute(
            roleId: $roleId,
            modelType: $this->targets->modelTypeFor($organizationId, $modelId),
            modelId: $modelId,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            organizationId: $organizationId,
            reason: (string) $validated['reason'],
        );

        return response()->json(status: 201);
    }
}
