<?php

declare(strict_types=1);

namespace Modules\Payroll\Infrastructure\Providers;

use Modules\Payroll\Application\Policies\PayrollAdjustmentPolicy;
use Modules\Payroll\Application\Policies\PayrollEntryPolicy;
use Modules\Payroll\Application\Policies\PayrollPeriodPolicy;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Shared\Module\BaseModuleServiceProvider;

final class PayrollServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Payroll';
    }

    /**
     * أحداث هذا الموديول تُستهلك من موديولات أخرى (Notifications، Reporting،
     * Audit)؛ داخل Payroll تُنشر من الإجراءات مباشرة بعد نجاح المعاملة.
     *
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            PayrollPeriod::class => PayrollPeriodPolicy::class,
            PayrollEntry::class => PayrollEntryPolicy::class,
            PayrollAdjustment::class => PayrollAdjustmentPolicy::class,
        ];
    }
}
