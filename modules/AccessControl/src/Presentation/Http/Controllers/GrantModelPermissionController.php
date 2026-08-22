<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\GrantModelPermissionAction;
use Modules\AccessControl\Presentation\Http\Requests\GrantModelPermissionRequest;

final class GrantModelPermissionController
{
    public function __construct(
        private readonly GrantModelPermissionAction $action,
    ) {}

    public function __invoke(GrantModelPermissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->action->execute(
            permissionName: (string) $validated['permission'],
            modelType: (string) $validated['model_type'],
            modelId: (string) $validated['model_id'],
        );

        return response()->json(status: 201);
    }
}
