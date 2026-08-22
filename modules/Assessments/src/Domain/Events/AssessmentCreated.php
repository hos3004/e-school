<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * أُنشئ اختبار جديد (مسودة متاحة للتحرير قبل فتح نافذة توفره).
 */
final class AssessmentCreated extends AssessmentEvent
{
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly ?string $courseId,
        public readonly string $type,
        public readonly int $totalScore,
        public readonly int $passingScore,
        public readonly int $maxAttempts,
        string $createdBy,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId ?? $createdBy, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.assessment_created';
    }

    public function payload(): array
    {
        return array_filter([
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'type' => $this->type,
            'total_score' => $this->totalScore,
            'passing_score' => $this->passingScore,
            'max_attempts' => $this->maxAttempts,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
