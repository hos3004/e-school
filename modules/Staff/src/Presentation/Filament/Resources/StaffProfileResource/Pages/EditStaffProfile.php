<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Resources\StaffProfileResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;

final class EditStaffProfile extends EditRecord
{
    protected static string $resource = StaffProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
