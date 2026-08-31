<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Filament\Resources\SessionReportResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\AcademicReports\Presentation\Filament\Resources\SessionReportResource;

final class ListSessionReports extends ListRecords
{
    protected static string $resource = SessionReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
