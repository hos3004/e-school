<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Discipline\Domain\ValueObjects\FollowupDisciplineData;

interface DisciplineFollowupQueries
{
    /** @param list<string> $enrollmentIds
     * @return array<string, FollowupDisciplineData> */
    public function forEnrollments(string $organizationId, array $enrollmentIds, CarbonImmutable $from, CarbonImmutable $until): array;
}
