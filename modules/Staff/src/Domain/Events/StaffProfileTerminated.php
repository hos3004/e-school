<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Events;

use Shared\Domain\DomainEvent;

final class StaffProfileTerminated extends DomainEvent
{
    public function __construct(
        public readonly string $staffProfileId,
        public readonly string $organizationId,
        public readonly string $userId,
        public readonly ?string $reason,
        public readonly bool $hadActiveContract,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'staff.profile_terminated';
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
            'staff_profile_id' => $this->staffProfileId,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'had_active_contract' => $this->hadActiveContract,
        ];
    }
}
