<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Contracts;

use Modules\Assignments\Domain\ValueObjects\AssignmentAudience;

interface AssignmentAudienceQueries
{
    public function forUser(string $organizationId, string $userId): AssignmentAudience;

    public function staffProfileBelongsToOrganization(string $organizationId, string $staffProfileId): bool;

    public function teacherIsAssignedToTarget(
        string $organizationId,
        string $userId,
        string $staffProfileId,
        string $courseId,
        ?string $groupId,
    ): bool;
}
