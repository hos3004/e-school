<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Payroll\Domain\Events\PayrollAdjustmentApproved;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * اعتماد تسوية مقترحة.
 *
 * قرار العميل: من يقترح التسوية لا يعتمدها — فصل صلاحيات صريح
 * تدعمه مصفوفة config('payroll.adjustments') وقيد قاعدة البيانات.
 */
final readonly class ApprovePayrollAdjustmentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(PayrollAdjustment $adjustment, ?string $actorId = null): PayrollAdjustment
    {
        if ($adjustment->approved_at !== null || $adjustment->rejected_at !== null) {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.already_decided',
                'payroll::actions.approve_adjustment.already_decided',
                ['adjustment_id' => $adjustment->id],
            );
        }

        /** @var PayrollPeriod|null $period */
        $period = PayrollPeriod::query()->find($adjustment->payroll_period_id);

        if ($period === null || !$period->status->acceptsAdjustments()) {
            throw BusinessRuleViolation::make(
                'payroll.period.frozen',
                'payroll::actions.approve_adjustment.period_frozen',
            );
        }

        $approverId = $actorId ?? (string) auth()->id();

        if (config('payroll.adjustments.requires_different_approver') === true
            && $approverId === (string) $adjustment->proposed_by) {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.self_approval',
                'payroll::actions.approve_adjustment.self_approval',
                ['proposed_by' => (string) $adjustment->proposed_by],
            );
        }

        $this->transaction->run(function () use ($adjustment, $approverId): void {
            $adjustment->forceFill([
                'approved_by' => $approverId,
                'approved_at' => CarbonImmutable::now('UTC'),
            ])->save();
        });

        $this->events->dispatch(new PayrollAdjustmentApproved(
            adjustmentId: $adjustment->id,
            organizationId: $adjustment->organization_id,
            payrollPeriodId: $adjustment->payroll_period_id,
            staffProfileId: $adjustment->staff_profile_id,
            type: $adjustment->type,
            amountMinorUnits: (int) $adjustment->amount,
            currency: (string) $adjustment->currency,
            approvedBy: $approverId,
        ));

        return $adjustment->refresh();
    }
}
