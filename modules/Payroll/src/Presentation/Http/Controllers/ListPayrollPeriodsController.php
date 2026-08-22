<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Payroll\Application\Queries\ListPayrollPeriodsQuery;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Presentation\Http\Resources\PayrollPeriodResource;

/**
 * عرض فترات المستحقات — قراءة فقط عبر Query Service.
 */
final class ListPayrollPeriodsController extends Controller
{
    public function __construct(
        private readonly ListPayrollPeriodsQuery $query,
    ) {}

    public function __invoke(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        return PayrollPeriodResource::collection(
            $this->query->handle(),
        );
    }
}
