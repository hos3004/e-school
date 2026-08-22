<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أُنشئ نشاط جديد وأُسند لطلاب مقرر أو مجموعة.
 */
final class AssignmentCreated extends DomainEvent
{
    public function __construct(
        public readonly string $assignmentId,
        public readonly string $organizationId,
        public readonly string $courseId,
        public readonly ?string $groupId,
        public readonly string $staffProfileId,
        public readonly int $maxScore,
        public readonly bool $allowsLate,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assignments.assignment_created';
    }

    public function module(): string
    {
        return 'Assignments';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'assignment_id' => $this->assignmentId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'group_id' => $this->groupId,
            'staff_profile_id' => $this->staffProfileId,
            'max_score' => $this->maxScore,
            'allows_late' => $this->allowsLate,
        ];
    }
}
