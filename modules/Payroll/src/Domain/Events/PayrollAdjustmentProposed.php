<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * قُترحت تسوية (مكافأة/خصم/تصحيح) — تنتظر اعتماد مشرفٍ غير مقترحها.
 */
final class PayrollAdjustmentProposed extends DomainEvent
{
    public function __construct(
        public readonly string $adjustmentId,
        public readonly string $organizationId,
        public readonly string $payrollPeriodId,
        public readonly string $staffProfileId,
        public readonly string $type,
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        public readonly string $proposedBy,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'payroll.adjustment.proposed';
    }

    public function module(): string
    {
        return 'Payroll';
    }

    public function payload(): array
    {
        return [
            'adjustment_id' => $this->adjustmentId,
            'organization_id' => $this->organizationId,
            'payroll_period_id' => $this->payrollPeriodId,
            'staff_profile_id' => $this->staffProfileId,
            'type' => $this->type,
            'amount_minor_units' => $this->amountMinorUnits,
            'currency' => $this->currency,
            'proposed_by' => $this->proposedBy,
        ];
    }
}
