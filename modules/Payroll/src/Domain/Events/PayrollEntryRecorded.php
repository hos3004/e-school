<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * سُجِّلت قيدة في دفتر المستحقات — القيود لا تُعدَّل بعد هذا الحدث أبدًا.
 */
final class PayrollEntryRecorded extends DomainEvent
{
    public function __construct(
        public readonly string $entryId,
        public readonly string $organizationId,
        public readonly string $payrollPeriodId,
        public readonly string $staffProfileId,
        public readonly ?string $sessionId,
        public readonly string $entryType,
        public readonly string $outcomeKey,
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'payroll.entry.recorded';
    }

    public function module(): string
    {
        return 'Payroll';
    }

    public function payload(): array
    {
        return [
            'entry_id' => $this->entryId,
            'organization_id' => $this->organizationId,
            'payroll_period_id' => $this->payrollPeriodId,
            'staff_profile_id' => $this->staffProfileId,
            'session_id' => $this->sessionId,
            'entry_type' => $this->entryType,
            'outcome_key' => $this->outcomeKey,
            'amount_minor_units' => $this->amountMinorUnits,
            'currency' => $this->currency,
        ];
    }
}
