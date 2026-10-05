<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $adjustmentId,
        string $actorId,
        string $reason,
    ): PayrollAdjustment {
        if (config('payroll.adjustments.requires_note') === true && trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.reason_required',
                'payroll::actions.reject_adjustment.reason_required',
            );
        }

        $adjustment = $this->transaction->run(function () use ($organizationId, $adjustmentId, $actorId, $reason): PayrollAdjustment {
            /** @var PayrollAdjustment|null $adjustment */
            $adjustment = PayrollAdjustment::query()
                ->forOrganization($organizationId)
                ->whereKey($adjustmentId)
                ->lockForUpdate()
                ->first();
            if ($adjustment === null) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.not_found',
                    'payroll::actions.reject_adjustment.not_found',
                );
            }

            if ($adjustment->approved_at !== null || $adjustment->rejected_at !== null) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.already_decided',
                    'payroll::actions.reject_adjustment.already_decided',
                    ['adjustment_id' => $adjustment->id],
                );
            }

            /** @var PayrollPeriod|null $period */
            $period = PayrollPeriod::query()
                ->forOrganization($organizationId)
                ->whereKey($adjustment->payroll_period_id)
                ->lockForUpdate()
                ->first();
            if ($period === null || !$period->status->acceptsAdjustments()) {
                throw BusinessRuleViolation::make(
                    'payroll.period.frozen',
                    'payroll::actions.reject_adjustment.period_frozen',
                );
            }

            if (config('payroll.adjustments.requires_different_approver') === true
                && $actorId === (string) $adjustment->proposed_by) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.self_approval',
                    'payroll::actions.reject_adjustment.self_approval',
                    ['proposed_by' => (string) $adjustment->proposed_by],
                );
            }

            $adjustment->forceFill([
                'rejected_by' => $actorId,
                'rejected_at' => CarbonImmutable::now('UTC'),
                'rejection_reason' => $reason,
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'payroll.adjustment.rejected',
                auditableType: 'payroll_adjustments',
                auditableId: (string) $adjustment->getKey(),
                oldValues: [
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ],
                newValues: [
                    'rejected_by' => $actorId,
                    'rejected_at' => $adjustment->rejected_at?->toIso8601String(),
                    'rejection_reason' => trim($reason),
                ],
                reason: trim($reason),
            );

            return $adjustment;
        });

        $this->events->dispatch(new PayrollAdjustmentRejected(
            adjustmentId: $adjustment->id,
            organizationId: $adjustment->organization_id,
            payrollPeriodId: $adjustment->payroll_period_id,
            staffProfileId: $adjustment->staff_profile_id,
            type: $adjustment->type,
            amountMinorUnits: (int) $adjustment->amount,
            currency: (string) $adjustment->currency,
            rejectedBy: $actorId,
            reason: $reason,
            actorId: $actorId,
        ));

        return $adjustment->refresh();
    }
}
