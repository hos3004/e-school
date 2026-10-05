<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\GrantModelPermissionAction;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentTargetScope;
use Modules\AccessControl\Presentation\Http\Requests\GrantModelPermissionRequest;
use Modules\AccessControl\Presentation\Http\Support\ActorOrganization;

final class GrantModelPermissionController
{
    public function __construct(
        private readonly GrantModelPermissionAction $action,
        private readonly RoleAssignmentTargetScope $targets,
    ) {}

    public function __invoke(GrantModelPermissionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $organizationId = ActorOrganization::from($request);
        $modelId = (string) $validated['model_id'];

        $this->action->execute(
            permissionName: (string) $validated['permission'],
            modelType: $this->targets->modelTypeFor($organizationId, $modelId),
            modelId: $modelId,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            organizationId: $organizationId,
            reason: (string) $validated['reason'],
        );

        return response()->json(status: 201);
    }
}
