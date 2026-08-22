<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * صُحّحت محاولة وأُعلنت النتيجة — يُستهلك لتحديث حالة الطالب الأكاديمية
 * (خاصة اختبارات التحديد وإعادة التنشيط) وللإشعارات.
 */
final class AttemptGraded extends AssessmentEvent
{
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly string $attemptId,
        public readonly string $studentProfileId,
        public readonly int $attemptNumber,
        public readonly int $score,
        public readonly bool $passed,
        public readonly ?string $reactivationRequestId = null,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.attempt_graded';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'attempt_id' => $this->attemptId,
            'student_profile_id' => $this->studentProfileId,
            'attempt_number' => $this->attemptNumber,
            'score' => $this->score,
            'passed' => $this->passed,
            'reactivation_request_id' => $this->reactivationRequestId,
        ];
    }
}
