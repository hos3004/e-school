<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources\StudentDashboardResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Reporting\Presentation\Filament\Resources\StudentDashboardResource;

final class ListStudentDashboards extends ListRecords
{
    protected static string $resource = StudentDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
