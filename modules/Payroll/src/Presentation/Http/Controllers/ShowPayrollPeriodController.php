<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Presentation\Http\Resources\PayrollPeriodResource;

/**
 * عرض فترة مستحقات واحدة.
 */
final class ShowPayrollPeriodController extends Controller
{
    public function __invoke(Request $request, string $period): PayrollPeriodResource
    {
        $periodModel = PayrollPeriod::query()
            ->forOrganization((string) $request->user()->getAttribute('organization_id'))
            ->findOrFail($period);

        Gate::authorize('view', $periodModel);

        return new PayrollPeriodResource($periodModel);
    }
}
