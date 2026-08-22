<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * عُدّلت بيانات اختبار قائم — يُستهلك للتدقيق ومزامنة العرض لدى الطلاب.
 */
final class AssessmentUpdated extends AssessmentEvent
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        string $assessmentId,
        string $organizationId,
        public readonly array $changedFields,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($assessmentId, $organizationId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'assessments.assessment_updated';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
            'changed_fields' => $this->changedFields,
        ];
    }
}
