<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources\ReportEventLogResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Reporting\Presentation\Filament\Resources\ReportEventLogResource;

/**
 * صفحة فهرس ReportEventLogResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListReportEventLogs extends ListRecords
{
    protected static string $resource = ReportEventLogResource::class;
}
