<?php

declare(strict_types=1);

namespace App\Infrastructure\AccessControl;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentTargetScope;
use Modules\Identity\Domain\Contracts\UserQueryService;

/** Composition adapter: validates Identity accounts without exposing its model. */
final readonly class IdentityRoleAssignmentTargetScope implements RoleAssignmentTargetScope
{
    public function __construct(
        private UserQueryService $users,
    ) {}

    public function modelTypeFor(string $organizationId, string $targetId): string
    {
        $user = $this->users->findSummary($targetId);

        if ($user === null || !hash_equals($organizationId, $user->organizationId)) {
            throw (new ModelNotFoundException)->setModel('user', [$targetId]);
        }

        return $this->users->modelType();
    }
}
