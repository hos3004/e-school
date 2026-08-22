<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;

final class EditHoliday extends EditRecord
{
    protected static string $resource = HolidayFilamentResource::class;
}
