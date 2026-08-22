<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;

final class ManageStaffProfiles extends ListRecords
{
    protected static string $resource = StaffProfileResource::class;
}
