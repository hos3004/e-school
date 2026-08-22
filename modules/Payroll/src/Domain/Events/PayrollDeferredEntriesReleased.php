<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * حُررت قيود مؤجَّلة بعد إقامة حصة التلافي — أصبحت مستحقة فعلًا.
 */
final class PayrollDeferredEntriesReleased extends DomainEvent
{
    /**
     * @param list<string> $entryIds
     */
    public function __construct(
        public readonly array $entryIds,
        public readonly string $organizationId,
        public readonly string $payrollPeriodId,
        public readonly string $staffProfileId,
        public readonly string $makeupSessionId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'payroll.deferred_entries.released';
    }

    public function module(): string
    {
        return 'Payroll';
    }

    public function payload(): array
    {
        return [
            'entry_ids' => $this->entryIds,
            'released_count' => count($this->entryIds),
            'organization_id' => $this->organizationId,
            'payroll_period_id' => $this->payrollPeriodId,
            'staff_profile_id' => $this->staffProfileId,
            'makeup_session_id' => $this->makeupSessionId,
        ];
    }
}
