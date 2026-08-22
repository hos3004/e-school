<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;

final class ListHolidays extends ListRecords
{
    protected static string $resource = HolidayFilamentResource::class;

    protected function getTableQuery(): ?Builder
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');

        return HolidayFilamentResource::getModel()::query()
            ->when($organizationId !== null, static fn ($query) => $query->forOrganization((string) $organizationId));
    }
}
