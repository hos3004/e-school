<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Contracts;

use Modules\Assignments\Domain\ValueObjects\StudentAssignmentResult;

interface StudentAssignmentResultQueries
{
    /** @return list<StudentAssignmentResult> Only this student's current study; staff limits results to their authored assignments. */
    public function forStudent(string $organizationId, string $studentUserId, ?string $staffProfileId = null): array;
}
