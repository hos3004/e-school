<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Application\Actions\ProposePayrollAdjustmentAction;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Presentation\Http\Requests\ProposePayrollAdjustmentRequest;
use Modules\Payroll\Presentation\Http\Resources\PayrollAdjustmentResource;
use Shared\ValueObjects\Money;

/**
 * اقتراح تسوية جديدة على فترة.
 */
final class ProposePayrollAdjustmentController extends Controller
{
    public function __construct(
        private readonly ProposePayrollAdjustmentAction $action,
    ) {}

    public function __invoke(ProposePayrollAdjustmentRequest $request): PayrollAdjustmentResource
    {
        Gate::authorize('create', PayrollAdjustment::class);

        $adjustment = $this->action->execute(
            organizationId: (string) $request->user()?->getAttribute('organization_id'),
            payrollPeriodId: (string) $request->route('period'),
            staffProfileId: (string) $request->validated('staff_profile_id'),
            type: (string) $request->validated('type'),
            amount: Money::fromMajor(
                (string) $request->validated('amount'),
                (string) config('payroll.currency'),
            ),
            reason: (string) $request->validated('reason'),
            referencesPeriodId: $request->validated('references_period_id'),
            actorId: (string) $request->user()->getAuthIdentifier(),
        );

        return new PayrollAdjustmentResource($adjustment);
    }
}
