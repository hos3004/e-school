<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Application\Actions\ApprovePayrollAdjustmentAction;
use Modules\Payroll\Application\Actions\ProposePayrollAdjustmentAction;
use Modules\Payroll\Application\Actions\RejectPayrollAdjustmentAction;
use Modules\Payroll\Domain\Contracts\TeacherDuesOperations;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesMoney;

final readonly class TeacherDuesOperationService implements TeacherDuesOperations
{
    public function __construct(private ProposePayrollAdjustmentAction $propose, private ApprovePayrollAdjustmentAction $approve,
        private RejectPayrollAdjustmentAction $reject) {}

    public function propose(Authenticatable $actor, string $periodId, array $data): array
    {
        $org = (string) $actor->getAttribute('organization_id');
        $period = PayrollPeriod::query()->forOrganization($org)->findOrFail($periodId);
        Gate::forUser($actor)->authorize('view', $period);
        Gate::forUser($actor)->authorize('create', PayrollAdjustment::class);
        $amount = TeacherDuesMoney::fromMajor((string) $data['amount'], (string) config('payroll.currency'));
        // A deduction is entered as a positive magnitude and posted with its sign.
        if ($data['type'] === 'deduction') {
            $amount = $amount->negated();
        }
        $item = $this->propose->execute($org, $periodId, (string) $data['staff_profile_id'], (string) $data['type'],
            $amount, (string) $data['reason'], $data['references_period_id'] ?? null, (string) $actor->getAuthIdentifier());

        return $this->identity($item);
    }

    public function decide(Authenticatable $actor, string $adjustmentId, bool $approve, string $reason): array
    {
        $org = (string) $actor->getAttribute('organization_id');
        $item = PayrollAdjustment::query()->forOrganization($org)->findOrFail($adjustmentId);
        Gate::forUser($actor)->authorize($approve ? 'approve' : 'reject', $item);
        $result = $approve
            ? $this->approve->execute($org, $adjustmentId, (string) $actor->getAuthIdentifier(), $reason)
            : $this->reject->execute($org, $adjustmentId, (string) $actor->getAuthIdentifier(), $reason);

        return $this->identity($result);
    }

    /** @return array{periodId: string, staffProfileId: string, adjustmentId: string} */
    private function identity(PayrollAdjustment $item): array
    {
        return ['periodId' => $item->payroll_period_id, 'staffProfileId' => $item->staff_profile_id, 'adjustmentId' => $item->id];
    }
}
