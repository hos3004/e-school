<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources\PayrollPeriodResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Presentation\Filament\Resources\PayrollPeriodResource;

/**
 * صفحة فهرس PayrollPeriodResource — كان المورد مُعلنًا في اللوحة بلا صفحات، فلا مسار له ولا
 * سبيل لفتحه رغم ظهوره في الشيفرة.
 */
final class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;
}
