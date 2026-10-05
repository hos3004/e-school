<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Contracts;

interface SchoolClockQueries
{
    /** @return array{timezone: string, week_starts_at: int} */
    public function forOrganization(string $organizationId): array;
}
