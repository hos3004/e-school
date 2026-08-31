<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Events;

use Shared\Domain\DomainEvent;

final class StudentAssignedToGroup extends DomainEvent
{
    /**
     * @param list<string> $teacherUserIds
     */
    public function __construct(
        public readonly string $membershipId,
        public readonly string $groupId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $studentUserId,
        public readonly array $teacherUserIds,
        public readonly string $programId,
        public readonly ?string $courseId = null,
        ?string $actorId = null,
        ?string $correlationId = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'student.assigned_to_group';
    }

    public function module(): string
    {
        return 'Groups';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'membership_id' => $this->membershipId,
            'group_id' => $this->groupId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'student_user_id' => $this->studentUserId,
            'teacher_user_ids' => $this->teacherUserIds,
            'program_id' => $this->programId,
            'course_id' => $this->courseId,
            'reason' => $this->reason,
        ];
    }
}
