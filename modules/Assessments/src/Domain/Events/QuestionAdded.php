<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * أُضيف سؤال إلى اختبار قائم.
 */
final class QuestionAdded extends AssessmentEvent
{
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly string $questionId,
        public readonly string $type,
        public readonly int $score,
        public readonly int $sortOrder,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.question_added';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'question_id' => $this->questionId,
            'type' => $this->type,
            'score' => $this->score,
            'sort_order' => $this->sortOrder,
        ];
    }
}
