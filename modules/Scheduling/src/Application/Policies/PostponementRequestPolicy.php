<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Scheduling\Domain\Models\PostponementRequest;

final class PostponementRequestPolicy
{
    public function request(Authenticatable $user): bool
    {
        return $user->can('session.postpone.request');
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('session.postpone.approve');
    }

    public function view(Authenticatable $user, PostponementRequest $request): bool
    {
        return $user->can('session.postpone.approve')
            && $request->organization_id === $user->getAttribute('organization_id');
    }

    public function approve(Authenticatable $user, PostponementRequest $request): bool
    {
        return $this->view($user, $request);
    }
}
