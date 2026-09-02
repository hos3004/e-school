<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Filament\Resources\PayrollAdjustmentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Application\Actions\ProposePayrollAdjustmentAction;
use Modules\Payroll\Presentation\Filament\Resources\PayrollAdjustmentResource;
use Shared\ValueObjects\Money;

final class CreatePayrollAdjustment extends CreateRecord
{
    protected static string $resource = PayrollAdjustmentResource::class;

    /**
     * الاقتراح يمر عبر `ProposePayrollAdjustmentAction` لا عبر إنشاء مباشر:
     * هو الذي يتحقق من نوع التسوية المسموح، ومن سقف النسبة قبل التصعيد، ويكتب
     * قيد التدقيق. الحفظ المباشر كان سيتجاوز ذلك كله.
     *
     * @param array<string, mixed> $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $organizationId = (string) session('organization_id');
        abort_if($organizationId === '', 403);

        return app(ProposePayrollAdjustmentAction::class)->execute(
            $organizationId,
            (string) $data['payroll_period_id'],
            (string) $data['staff_profile_id'],
            (string) $data['type'],
            // المبلغ يُدخَل بالوحدة الكبرى ويُخزَّن بالصغرى، كما في بقية الدفتر.
            Money::of((int) round(((float) $data['amount']) * 100)),
            (string) $data['reason'],
            null,
            (string) auth()->id(),
        );
    }
}
