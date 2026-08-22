<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;

final class CreateStaffProfile extends CreateRecord
{
    protected static string $resource = StaffProfileResource::class;
}
