<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * بدأ طالب محاولة اختبار — يُستهلك للإشعارات ومتابعة النشاط.
 */
final class AttemptStarted extends AssessmentEvent
{
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly string $attemptId,
        public readonly string $studentProfileId,
        public readonly int $attemptNumber,
        public readonly ?int $durationMinutes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.attempt_started';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'attempt_id' => $this->attemptId,
            'student_profile_id' => $this->studentProfileId,
            'attempt_number' => $this->attemptNumber,
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
