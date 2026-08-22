<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;

final class ListStaffProfiles extends ListRecords
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
