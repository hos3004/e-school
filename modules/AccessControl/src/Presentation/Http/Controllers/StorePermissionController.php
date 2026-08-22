<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Modules\AccessControl\Application\Actions\CreatePermissionAction;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Presentation\Http\Requests\StorePermissionRequest;
use Modules\AccessControl\Presentation\Http\Resources\PermissionResource;

final class StorePermissionController
{
    public function __construct(
        private readonly CreatePermissionAction $action,
    ) {}

    public function __invoke(StorePermissionRequest $request): PermissionResource
    {
        $validated = $request->validated();

        $permission = $this->action->execute(
            name: (string) $validated['name'],
            guard: GuardName::from((string) ($validated['guard_name'] ?? 'web')),
            module: $validated['module'] ?? null,
            description: $validated['description'] ?? null,
        );

        return new PermissionResource($permission);
    }
}
