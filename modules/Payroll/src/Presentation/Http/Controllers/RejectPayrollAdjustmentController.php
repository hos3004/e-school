<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Application\Actions\RejectPayrollAdjustmentAction;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Presentation\Http\Requests\RejectPayrollAdjustmentRequest;
use Modules\Payroll\Presentation\Http\Resources\PayrollAdjustmentResource;

/**
 * رفض تسوية مقترحة مع كتابة السبب.
 */
final class RejectPayrollAdjustmentController extends Controller
{
    public function __construct(
        private readonly RejectPayrollAdjustmentAction $action,
    ) {}

    public function __invoke(RejectPayrollAdjustmentRequest $request, string $adjustment): PayrollAdjustmentResource
    {
        $adjustmentModel = PayrollAdjustment::query()->findOrFail($adjustment);

        Gate::authorize('reject', $adjustmentModel);

        return new PayrollAdjustmentResource(
            $this->action->execute($adjustmentModel, (string) $request->validated('reason')),
        );
    }
}
