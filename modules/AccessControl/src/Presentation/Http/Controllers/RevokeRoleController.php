<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\RevokeRoleAction;
use Modules\AccessControl\Presentation\Http\Requests\RevokeRoleRequest;

final class RevokeRoleController
{
    public function __construct(
        private readonly RevokeRoleAction $action,
    ) {}

    public function __invoke(RevokeRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->action->execute(
            roleId: (string) $validated['role_id'],
            modelType: (string) $validated['model_type'],
            modelId: (string) $validated['model_id'],
        );

        return response()->json(status: 204);
    }
}
