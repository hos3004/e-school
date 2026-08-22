<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources\PayrollEntryResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Presentation\Filament\Resources\PayrollEntryResource;

final class ListPayrollEntries extends ListRecords
{
    protected static string $resource = PayrollEntryResource::class;

    /**
     * لا زر إنشاء — القيود تُولَّد من إقفال الحصص فقط.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
