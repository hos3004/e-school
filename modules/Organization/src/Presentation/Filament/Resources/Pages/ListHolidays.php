<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Modules\Organization\Domain\Models\Holiday;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;

final class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayFilamentResource::class;

    /** @return Builder<Holiday> */
    protected function getTableQuery(): Builder
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        $query = Holiday::query();

        if ($organizationId !== null) {
            $query->forOrganization((string) $organizationId);
        }

        return $query;
    }
}
