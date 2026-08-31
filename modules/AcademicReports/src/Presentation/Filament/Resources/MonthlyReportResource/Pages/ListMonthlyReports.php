<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\AcademicReports\Presentation\Filament\Resources\MonthlyReportResource;

final class ListMonthlyReports extends ListRecords
{
    protected static string $resource = MonthlyReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
