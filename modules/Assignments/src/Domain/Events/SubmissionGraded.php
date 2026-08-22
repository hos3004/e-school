<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * رصد المعلم درجة التسليم مع تغذيته الراجعة — حالة نهائية للتسليم.
 */
final class SubmissionGraded extends DomainEvent
{
    public function __construct(
        public readonly string $submissionId,
        public readonly string $assignmentId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly int $score,
        public readonly int $maxScore,
        public readonly bool $isLate,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assignments.submission_graded';
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
            'submission_id' => $this->submissionId,
            'assignment_id' => $this->assignmentId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'score' => $this->score,
            'max_score' => $this->maxScore,
            'is_late' => $this->isLate,
        ];
    }
}
