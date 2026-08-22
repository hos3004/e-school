<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Organization\Presentation\Filament\Resources\AcademicCalendarFilamentResource;

final class EditAcademicCalendar extends EditRecord
{
    protected static string $resource = AcademicCalendarFilamentResource::class;
}
