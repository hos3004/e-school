<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources\PayrollAdjustmentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Presentation\Filament\Resources\PayrollAdjustmentResource;

final class ListPayrollAdjustments extends ListRecords
{
    protected static string $resource = PayrollAdjustmentResource::class;

    /** @return array<int, mixed> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
