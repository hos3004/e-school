<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Events;

use Modules\Staff\Domain\Enums\RateScope;
use Shared\Domain\DomainEvent;
use Shared\ValueObjects\Money;

final class TeacherRateCreated extends DomainEvent
{
    public function __construct(
        public readonly string $rateId,
        public readonly string $contractId,
        public readonly string $staffProfileId,
        public readonly RateScope $scope,
        public readonly ?string $programId,
        public readonly ?string $courseId,
        public readonly ?string $sessionType,
        public readonly Money $amount,
        public readonly string $effectiveFrom,
        public readonly ?string $effectiveTo,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'staff.rate_created';
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
            'rate_id' => $this->rateId,
            'contract_id' => $this->contractId,
            'staff_profile_id' => $this->staffProfileId,
            'scope' => $this->scope->value,
            'program_id' => $this->programId,
            'course_id' => $this->courseId,
            'session_type' => $this->sessionType,
            'amount_minor' => $this->amount->minorUnits,
            'currency' => $this->amount->currency,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
