<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Queries;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Payroll\Domain\Models\PayrollEntry;

/**
 * استعلام قراءة لقيود الدفتر — يطبّق نطاق المؤسسة ويقبل تصفية اختيارية.
 */
final readonly class ListPayrollEntriesQuery
{
    public function __construct(
        private Guard $auth,
    ) {}

    /** @return Collection<int, PayrollEntry> */
    public function handle(string $payrollPeriodId = '', string $staffProfileId = ''): Collection
    {
        $organizationId = (string) $this->auth->user()?->getAttribute('organization_id');

        return PayrollEntry::query()
            ->when($organizationId !== '', fn (Builder $q): Builder => $q->forOrganization($organizationId))
            ->when($payrollPeriodId !== '', fn (Builder $q): Builder => $q->where('payroll_period_id', $payrollPeriodId))
            ->when($staffProfileId !== '', fn (Builder $q): Builder => $q->forStaff($staffProfileId))
            ->latest('created_at')
            ->get();
    }
}
