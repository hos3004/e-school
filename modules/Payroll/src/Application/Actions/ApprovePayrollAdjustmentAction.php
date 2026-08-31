<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $adjustmentId,
        string $actorId,
        string $reason,
    ): PayrollAdjustment {
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
                    'payroll::actions.approve_adjustment.not_found',
                );
            }

            if ($adjustment->approved_at !== null || $adjustment->rejected_at !== null) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.already_decided',
                    'payroll::actions.approve_adjustment.already_decided',
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
                    'payroll::actions.approve_adjustment.period_frozen',
                );
            }

            if (config('payroll.adjustments.requires_different_approver') === true
                && $actorId === (string) $adjustment->proposed_by) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.self_approval',
                    'payroll::actions.approve_adjustment.self_approval',
                    ['proposed_by' => (string) $adjustment->proposed_by],
                );
            }

            $adjustment->forceFill([
                'approved_by' => $actorId,
                'approved_at' => CarbonImmutable::now('UTC'),
            ])->save();
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'payroll.adjustment.approved',
                auditableType: 'payroll_adjustments',
                auditableId: (string) $adjustment->getKey(),
                oldValues: ['approved_by' => null, 'approved_at' => null],
                newValues: [
                    'approved_by' => $actorId,
                    'approved_at' => $adjustment->approved_at?->toIso8601String(),
                ],
                reason: trim($reason),
            );

            return $adjustment;
        });

        $this->events->dispatch(new PayrollAdjustmentApproved(
            adjustmentId: $adjustment->id,
            organizationId: $adjustment->organization_id,
            payrollPeriodId: $adjustment->payroll_period_id,
            staffProfileId: $adjustment->staff_profile_id,
            type: $adjustment->type,
            amountMinorUnits: (int) $adjustment->amount,
            currency: (string) $adjustment->currency,
            approvedBy: $actorId,
            actorId: $actorId,
        ));

        return $adjustment->refresh();
    }
}
