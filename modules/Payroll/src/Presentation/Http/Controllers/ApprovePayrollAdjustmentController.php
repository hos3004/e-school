<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Application\Actions\ApprovePayrollAdjustmentAction;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Presentation\Http\Requests\ApprovePayrollAdjustmentRequest;
use Modules\Payroll\Presentation\Http\Resources\PayrollAdjustmentResource;

/**
 * اعتماد تسوية مقترحة — من اقترحها لا يعتمدها (قيد مالي).
 */
final class ApprovePayrollAdjustmentController extends Controller
{
    public function __construct(
        private readonly ApprovePayrollAdjustmentAction $action,
    ) {}

    public function __invoke(ApprovePayrollAdjustmentRequest $request, string $adjustment): PayrollAdjustmentResource
    {
        $organizationId = (string) $request->user()->getAttribute('organization_id');
        $adjustmentModel = PayrollAdjustment::query()
            ->forOrganization($organizationId)
            ->findOrFail($adjustment);

        Gate::authorize('approve', $adjustmentModel);

        return new PayrollAdjustmentResource(
            $this->action->execute(
                organizationId: $organizationId,
                adjustmentId: (string) $adjustmentModel->getKey(),
                actorId: (string) $request->user()->getAuthIdentifier(),
                reason: (string) $request->validated('reason'),
            ),
        );
    }
}
