<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Payroll\Application\Queries\ListPayrollEntriesQuery;
use Modules\Payroll\Presentation\Http\Requests\ListPayrollEntriesRequest;
use Modules\Payroll\Presentation\Http\Resources\PayrollEntryResource;

/**
 * عرض قيود دفتر المستحقات — قراءة فقط مع تصفية اختيارية بالفترة أو الموظف.
 */
final class ListPayrollEntriesController extends Controller
{
    public function __construct(
        private readonly ListPayrollEntriesQuery $query,
    ) {}

    public function __invoke(ListPayrollEntriesRequest $request): AnonymousResourceCollection
    {
        return PayrollEntryResource::collection(
            $this->query->handle(
                (string) $request->validated('payroll_period_id'),
                (string) $request->validated('staff_profile_id'),
            ),
        );
    }
}
