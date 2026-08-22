<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Http\Resources\RoleResource;

final class ListRolesController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $this->authorizeOrAbort();

        return RoleResource::collection(
            Role::query()->orderBy('name')->get(),
        );
    }

    private function authorizeOrAbort(): void
    {
        $user = auth()->user();

        abort_unless($user !== null && $user->can('accesscontrol.roles.view_any'), 403);
    }
}
