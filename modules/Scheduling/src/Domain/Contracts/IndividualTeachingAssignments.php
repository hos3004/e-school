<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Contracts;

use Modules\Scheduling\Domain\ValueObjects\IndividualTeachingAssignment;

interface IndividualTeachingAssignments
{
    /** @return list<IndividualTeachingAssignment> */
    public function activeForTeacher(string $organizationId, string $staffProfileId): array;
}
