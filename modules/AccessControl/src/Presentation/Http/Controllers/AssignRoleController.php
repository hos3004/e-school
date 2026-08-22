<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\AssignRoleAction;
use Modules\AccessControl\Presentation\Http\Requests\AssignRoleRequest;

final class AssignRoleController
{
    public function __construct(
        private readonly AssignRoleAction $action,
    ) {}

    public function __invoke(AssignRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->action->execute(
            roleId: (string) $validated['role_id'],
            modelType: (string) $validated['model_type'],
            modelId: (string) $validated['model_id'],
        );

        return response()->json(status: 201);
    }
}
