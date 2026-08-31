<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Contracts;

use Modules\Assignments\Domain\ValueObjects\AssignmentAudience;

interface AssignmentAudienceQueries
{
    public function forUser(string $organizationId, string $userId): AssignmentAudience;

    public function staffProfileBelongsToOrganization(string $organizationId, string $staffProfileId): bool;

    public function targetBelongsToOrganization(
        string $organizationId,
        string $courseId,
        ?string $groupId,
    ): bool;

    public function teacherCanTeachTarget(
        string $organizationId,
        string $staffProfileId,
        string $courseId,
        ?string $groupId,
    ): bool;

    /** @return list<string> */
    public function studentProfileIdsForTarget(
        string $organizationId,
        string $courseId,
        ?string $groupId,
    ): array;

    public function teacherIsAssignedToTarget(
        string $organizationId,
        string $userId,
        string $staffProfileId,
        string $courseId,
        ?string $groupId,
    ): bool;
}
