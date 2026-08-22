<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\DeletePermissionAction;

final class DeletePermissionController
{
    public function __construct(
        private readonly DeletePermissionAction $action,
    ) {}

    public function __invoke(string $permissionId): JsonResponse
    {
        $this->action->execute($permissionId);

        return response()->json(status: 204);
    }
}
