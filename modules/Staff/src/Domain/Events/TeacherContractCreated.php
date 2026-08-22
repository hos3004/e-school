<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Events;

use Modules\Staff\Domain\Enums\ContractBasis;
use Shared\Domain\DomainEvent;
use Shared\ValueObjects\Money;

final class TeacherContractCreated extends DomainEvent
{
    public function __construct(
        public readonly string $contractId,
        public readonly string $organizationId,
        public readonly string $staffProfileId,
        public readonly ContractBasis $basis,
        public readonly ?Money $baseAmount,
        public readonly string $effectiveFrom,
        public readonly ?string $effectiveTo,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'staff.contract_created';
    }

    public function module(): string
    {
        return 'Staff';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'contract_id' => $this->contractId,
            'organization_id' => $this->organizationId,
            'staff_profile_id' => $this->staffProfileId,
            'basis' => $this->basis->value,
            'base_amount_minor' => $this->baseAmount?->minorUnits,
            'currency' => $this->baseAmount?->currency,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
