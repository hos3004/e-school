<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Presentation\Http\Resources\PermissionResource;

final class ListPermissionsController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $this->authorizeOrAbort();

        return PermissionResource::collection(
            Permission::query()->orderBy('name')->get(),
        );
    }

    private function authorizeOrAbort(): void
    {
        $user = auth()->user();

        abort_unless($user !== null && $user->can('accesscontrol.permissions.view_any'), 403);
    }
}
