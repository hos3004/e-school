<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Events;

use Shared\Domain\DomainEvent;

final class TeacherUnassignedFromGroup extends DomainEvent
{
    public function __construct(
        public readonly string $assignmentId,
        public readonly string $groupId,
        public readonly string $organizationId,
        public readonly string $staffProfileId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'groups.teacher_unassigned';
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
            'assignment_id' => $this->assignmentId,
            'group_id' => $this->groupId,
            'organization_id' => $this->organizationId,
            'staff_profile_id' => $this->staffProfileId,
        ];
    }
}
