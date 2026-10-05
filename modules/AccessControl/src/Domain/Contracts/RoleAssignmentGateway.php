<?php

declare(strict_types=1);

namespace Modules\AccessControl\Domain\Contracts;

/** منفذ إسناد دور مسمى دون تسريب نماذج AccessControl. */
interface RoleAssignmentGateway
{
    public function assignIfMissing(
        string $roleName,
        string $modelType,
        string $modelId,
        string $organizationId,
        ?string $actorId = null,
    ): bool;
}
