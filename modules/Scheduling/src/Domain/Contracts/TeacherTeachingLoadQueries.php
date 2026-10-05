<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Contracts;

use Modules\Scheduling\Domain\ValueObjects\TeachingLine;

/** حِمل التدريس السارِي لمعلم واحد: جداوله الفردية والجماعية وروابطه المعلقة. */
interface TeacherTeachingLoadQueries
{
    /** @return list<TeachingLine> */
    public function forTeacher(string $organizationId, string $staffProfileId): array;
}
