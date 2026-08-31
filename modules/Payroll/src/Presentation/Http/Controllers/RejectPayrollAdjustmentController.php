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
        $organizationId = (string) $request->user()->getAttribute('organization_id');
        $adjustmentModel = PayrollAdjustment::query()
            ->forOrganization($organizationId)
            ->findOrFail($adjustment);

        Gate::authorize('reject', $adjustmentModel);

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
