<?php

declare(strict_types=1);

namespace Modules\Payroll\Infrastructure\Providers;

use Modules\Payroll\Application\Listeners\RecordSessionPayrollEntry;
use Modules\Payroll\Application\Policies\PayrollAdjustmentPolicy;
use Modules\Payroll\Application\Policies\PayrollEntryPolicy;
use Modules\Payroll\Application\Policies\PayrollPeriodPolicy;
use Modules\Payroll\Application\Queries\TeacherEarningsQueryService;
use Modules\Payroll\Domain\Contracts\TeacherEarningsQueries;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Sessions\Domain\Events\SessionCancelled;
use Modules\Sessions\Domain\Events\SessionCompleted;
use Modules\Sessions\Domain\Events\SessionExcused;
use Modules\Sessions\Domain\Events\SessionNoShowRecorded;
use Modules\Sessions\Domain\Events\SessionPostponed;
use Modules\Sessions\Domain\Events\TeacherApologyDecided;
use Shared\Module\BaseModuleServiceProvider;

final class PayrollServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Payroll';
    }

    /**
     * أحداث دورة حياة الحصة هي ما يغذّي الدفتر.
     *
     * المستمع واحد لكل الأحداث لأنه لا يثق بالحدث في تحديد النتيجة: يقرأ
     * الحالة النهائية للحصة من عقد Sessions ثم يترجمها عبر
     * `config/payroll.php`. هذا يجعل وصول الحدث مرتين غير ضار، ويمنع
     * تضارب حدث قديم مع حالة حُدِّثت بعده.
     *
     * أحداث Payroll نفسها تُستهلك من موديولات أخرى (Reporting، Audit) ولا
     * تُسجَّل هنا؛ الإجراءات تنشرها مباشرة بعد نجاح المعاملة.
     *
     * @return array<string, list<class-string>>
     */
    protected function listeners(): array
    {
        $accrual = RecordSessionPayrollEntry::class;

        return [
            SessionCompleted::class => [$accrual],
            SessionNoShowRecorded::class => [$accrual],
            SessionExcused::class => [$accrual],
            SessionCancelled::class => [$accrual],
            SessionPostponed::class => [$accrual],
            TeacherApologyDecided::class => [$accrual],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            TeacherEarningsQueries::class => TeacherEarningsQueryService::class,
        ];
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
