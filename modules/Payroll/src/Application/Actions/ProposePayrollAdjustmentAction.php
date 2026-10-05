<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Payroll\Domain\Events\PayrollAdjustmentProposed;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;

/**
 * اقتراح تسوية (مكافأة/خصم/تصحيح) على فترة.
 *
 * فصل الصلاحيات: من يقترح لا يعتمد — الاعتماد في ApprovePayrollAdjustmentAction
 * بصلاحية مختلفة يفرضها القيد على مستوى قاعدة البيانات أيضًا.
 */
final readonly class ProposePayrollAdjustmentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private StaffQueries $staff,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        string $organizationId,
        string $payrollPeriodId,
        string $staffProfileId,
        string $type,
        Money $amount,
        string $reason,
        ?string $referencesPeriodId = null,
        ?string $actorId = null,
    ): PayrollAdjustment {
        /** @var list<string>|null $allowedTypes */
        $allowedTypes = config('payroll.adjustments.types');

        if (!is_array($allowedTypes) || !in_array($type, $allowedTypes, true)) {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.unknown_type',
                'payroll::actions.propose_adjustment.unknown_type',
                ['type' => $type],
            );
        }

        if (config('payroll.adjustments.requires_note') === true && trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.reason_required',
                'payroll::actions.propose_adjustment.reason_required',
            );
        }

        if ($amount->isZero() || ($amount->isNegative() && $type !== 'deduction')) {
            throw BusinessRuleViolation::make(
                'payroll.adjustment.invalid_amount',
                'payroll::actions.propose_adjustment.invalid_amount',
            );
        }

        $proposerId = $actorId ?? (string) auth()->id();

        $adjustment = $this->transaction->run(function () use (
            $organizationId,
            $payrollPeriodId,
            $staffProfileId,
            $type,
            $amount,
            $reason,
            $referencesPeriodId,
            $proposerId,
        ): PayrollAdjustment {
            /** @var PayrollPeriod|null $period */
            $period = PayrollPeriod::query()
                ->forOrganization($organizationId)
                ->whereKey($payrollPeriodId)
                ->lockForUpdate()
                ->first();

            if ($period === null) {
                throw BusinessRuleViolation::make(
                    'payroll.period.not_found',
                    'payroll::actions.propose_adjustment.period_not_found',
                );
            }

            if (!$period->status->acceptsAdjustments()) {
                throw BusinessRuleViolation::make(
                    'payroll.period.frozen',
                    'payroll::actions.propose_adjustment.period_frozen',
                    ['status' => $period->status->value],
                );
            }

            if ($this->staff->userIdForProfile($organizationId, $staffProfileId) === null) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.staff_not_found',
                    'payroll::actions.propose_adjustment.staff_not_found',
                );
            }

            if ($referencesPeriodId !== null && !PayrollPeriod::query()
                ->forOrganization($organizationId)
                ->whereKey($referencesPeriodId)
                ->exists()) {
                throw BusinessRuleViolation::make(
                    'payroll.adjustment.reference_period_not_found',
                    'payroll::actions.propose_adjustment.reference_period_not_found',
                );
            }

            /** @var PayrollAdjustment $adjustment */
            $adjustment = PayrollAdjustment::query()->create([
                'organization_id' => $organizationId,
                'payroll_period_id' => $payrollPeriodId,
                'staff_profile_id' => $staffProfileId,
                'type' => $type,
                'amount' => $amount->minorUnits,
                'currency' => $amount->currency,
                'reason' => $reason,
                'references_period_id' => $referencesPeriodId,
                'proposed_by' => $proposerId,
                'proposed_at' => CarbonImmutable::now('UTC'),
            ]);
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $proposerId,
                actorType: 'user',
                action: 'payroll.adjustment.proposed',
                auditableType: 'payroll_adjustments',
                auditableId: (string) $adjustment->getKey(),
                oldValues: null,
                newValues: [
                    'payroll_period_id' => $payrollPeriodId,
                    'staff_profile_id' => $staffProfileId,
                    'type' => $type,
                    'amount' => $amount->minorUnits,
                    'currency' => $amount->currency,
                    'references_period_id' => $referencesPeriodId,
                ],
                reason: trim($reason),
            );

            return $adjustment;
        });

        $this->events->dispatch(new PayrollAdjustmentProposed(
            adjustmentId: $adjustment->id,
            organizationId: $adjustment->organization_id,
            payrollPeriodId: $adjustment->payroll_period_id,
            staffProfileId: $adjustment->staff_profile_id,
            type: $adjustment->type,
            amountMinorUnits: $amount->minorUnits,
            currency: $amount->currency,
            proposedBy: $proposerId,
            actorId: $proposerId,
        ));

        return $adjustment;
    }
}
