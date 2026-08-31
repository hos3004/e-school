<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources\TeacherDashboardResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Reporting\Presentation\Filament\Resources\TeacherDashboardResource;

final class ListTeacherDashboards extends ListRecords
{
    protected static string $resource = TeacherDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
