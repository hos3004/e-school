<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Organization\Presentation\Filament\Resources\AcademicCalendarFilamentResource;

final class ListAcademicCalendars extends ListRecords
{
    protected static string $resource = AcademicCalendarFilamentResource::class;
}
