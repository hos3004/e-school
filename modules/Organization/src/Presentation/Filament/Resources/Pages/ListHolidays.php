<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;

final class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayFilamentResource::class;

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');

        return HolidayFilamentResource::getModel()::query()
            ->when($organizationId !== null, static fn ($query) => $query->forOrganization((string) $organizationId));
    }
}
