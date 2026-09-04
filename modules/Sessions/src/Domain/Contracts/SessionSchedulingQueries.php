<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\ValueObjects\SessionSchedulingData;
use Shared\ValueObjects\TimeRange;

interface SessionSchedulingQueries
{
    public function find(string $organizationId, string $sessionId): ?SessionSchedulingData;

    /**
     * @param list<string> $studentProfileIds
     * @return list<string>
     */
    public function conflictsFor(
        string $organizationId,
        TimeRange $range,
        ?string $staffProfileId = null,
        ?string $groupId = null,
        array $studentProfileIds = [],
        ?string $ignoreSessionId = null,
    ): array;

    /** @return list<SessionSchedulingData> */
    public function bookingsForTeacher(
        string $organizationId,
        string $staffProfileId,
        CarbonImmutable $from,
        CarbonImmutable $until,
    ): array;
}
