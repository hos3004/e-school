<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Events;

use Shared\Domain\DomainEvent;

final class StudentLeftGroup extends DomainEvent
{
    public function __construct(
        public readonly string $membershipId,
        public readonly string $groupId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'groups.student_left';
    }

    public function module(): string
    {
        return 'Groups';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'membership_id' => $this->membershipId,
            'group_id' => $this->groupId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'reason' => $this->reason,
        ];
    }
}
