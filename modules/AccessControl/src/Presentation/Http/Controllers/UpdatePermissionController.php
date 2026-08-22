<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Modules\AccessControl\Application\Actions\UpdatePermissionAction;
use Modules\AccessControl\Presentation\Http\Requests\UpdatePermissionRequest;
use Modules\AccessControl\Presentation\Http\Resources\PermissionResource;

final class UpdatePermissionController
{
    public function __construct(
        private readonly UpdatePermissionAction $action,
    ) {}

    public function __invoke(UpdatePermissionRequest $request, string $permissionId): PermissionResource
    {
        $validated = $request->validated();

        $permission = $this->action->execute(
            permissionId: $permissionId,
            name: $validated['name'] ?? null,
            module: array_key_exists('module', $validated) ? $validated['module'] : null,
            description: $validated['description'] ?? null,
        );

        return new PermissionResource($permission);
    }
}
