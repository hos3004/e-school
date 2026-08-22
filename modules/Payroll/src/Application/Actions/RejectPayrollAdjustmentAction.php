<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Payroll\Domain\Events\PayrollAdjustmentRejected;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * رفض تسوية مقترحة مع كتابة السبب.
 *
 * الرفض لا يحذف ولا يعدّل — التسوية تبقى موثّقة بحالتها المرفوضة،
 * والتصحيح إن لزم يكون بتسوية بديلة جديدة. من يقترح لا يرفض اقتراحه.
 */
final readonly class RejectPayrollAdjustmentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(PayrollAdjustment $adjustment, string $reason, ?string $actorId = null): PayrollAdjustment
    {
        if ($adjustment->approved_at !== null || $adjustment->rejected_at !== null) {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.already_decided',
                'payroll::actions.reject_adjustment.already_decided',
                ['adjustment_id' => $adjustment->id],
            );
        }

        if (config('payroll.adjustments.requires_note') === true && trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.reason_required',
                'payroll::actions.reject_adjustment.reason_required',
            );
        }

        /** @var PayrollPeriod|null $period */
        $period = PayrollPeriod::query()->find($adjustment->payroll_period_id);

        if ($period === null || !$period->status->acceptsAdjustments()) {
            throw BusinessRuleViolation::make(
                'payroll.period.frozen',
                'payroll::actions.reject_adjustment.period_frozen',
            );
        }

        $rejecterId = $actorId ?? (string) auth()->id();

        if (config('payroll.adjustments.requires_different_approver') === true
            && $rejecterId === (string) $adjustment->proposed_by) {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.self_approval',
                'payroll::actions.reject_adjustment.self_approval',
                ['proposed_by' => (string) $adjustment->proposed_by],
            );
        }

        $this->transaction->run(function () use ($adjustment, $reason, $rejecterId): void {
            $adjustment->forceFill([
                'rejected_by' => $rejecterId,
                'rejected_at' => CarbonImmutable::now('UTC'),
                'rejection_reason' => $reason,
            ])->save();
        });

        $this->events->dispatch(new PayrollAdjustmentRejected(
            adjustmentId: $adjustment->id,
            organizationId: $adjustment->organization_id,
            payrollPeriodId: $adjustment->payroll_period_id,
            staffProfileId: $adjustment->staff_profile_id,
            type: $adjustment->type,
            amountMinorUnits: (int) $adjustment->amount,
            currency: (string) $adjustment->currency,
            rejectedBy: $rejecterId,
            reason: $reason,
        ));

        return $adjustment->refresh();
    }
}
