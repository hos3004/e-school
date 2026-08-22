<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * حُذف سؤال من اختبار.
 */
final class QuestionRemoved extends AssessmentEvent
{
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly string $questionId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.question_removed';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'question_id' => $this->questionId,
        ];
    }
}
