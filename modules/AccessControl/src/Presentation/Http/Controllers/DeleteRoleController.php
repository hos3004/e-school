<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AccessControl\Application\Actions\DeleteRoleAction;

final class DeleteRoleController
{
    public function __construct(
        private readonly DeleteRoleAction $action,
    ) {}

    public function __invoke(string $roleId): JsonResponse
    {
        $this->action->execute($roleId);

        return response()->json(status: 204);
    }
}
