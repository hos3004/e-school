<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Queries;

use Carbon\CarbonInterface;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Organization\Domain\Enums\Weekday;
use Modules\Organization\Domain\Models\Organization;

final class SchoolClockQueryService implements SchoolClockQueries
{
    public function forOrganization(string $organizationId): array
    {
        $organization = Organization::query()->findOrFail($organizationId);

        return [
            'timezone' => $organization->default_timezone,
            'week_starts_at' => match ($organization->weekStartsOn()) {
                Weekday::Saturday => CarbonInterface::SATURDAY,
                Weekday::Sunday => CarbonInterface::SUNDAY,
                Weekday::Monday => CarbonInterface::MONDAY,
            },
        ];
    }
}
