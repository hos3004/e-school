<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * رُفضت تسوية مقترحة — تبقى في السجل للتوثيق ولا تؤثر على الفترة.
 */
final class PayrollAdjustmentRejected extends DomainEvent
{
    public function __construct(
        public readonly string $adjustmentId,
        public readonly string $organizationId,
        public readonly string $payrollPeriodId,
        public readonly string $staffProfileId,
        public readonly string $type,
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        public readonly string $rejectedBy,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'payroll.adjustment.rejected';
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
            'rejected_by' => $this->rejectedBy,
            'reason' => $this->reason,
        ];
    }
}
