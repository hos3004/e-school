<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * سلّم الطالب محاولته — بعد هذا الحدث لا تعديل على الإجابات.
 */
final class AttemptSubmitted extends AssessmentEvent
{
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly string $attemptId,
        public readonly string $studentProfileId,
        public readonly int $attemptNumber,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.attempt_submitted';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'attempt_id' => $this->attemptId,
            'student_profile_id' => $this->studentProfileId,
            'attempt_number' => $this->attemptNumber,
        ];
    }
}
