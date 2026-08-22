<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Queries;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Payroll\Domain\Models\PayrollPeriod;

/**
 * استعلام قراءة لفترات المستحقات مرتبة من الأحدث — يطبّق نطاق المؤسسة تلقائيًا.
 */
final readonly class ListPayrollPeriodsQuery
{
    public function __construct(
        private Guard $auth,
    ) {}

    /**
     * @return Collection<int, PayrollPeriod>
     */
    public function handle(): Collection
    {
        $organizationId = (string) $this->auth->user()?->getAttribute('organization_id');

        return PayrollPeriod::query()
            ->when($organizationId !== '', fn (Builder $q): Builder => $q->forOrganization($organizationId))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }
}
