<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

/**
 * أُرشف الاختبار (حذف ناعم) — لم يعد متاحًا للطلاب وتبقى بياناته للتدقيق.
 */
final class AssessmentArchived extends AssessmentEvent
{
    public function name(): string
    {
        return 'assessments.assessment_archived';
    }

    public function payload(): array
    {
        return [
            'assessment_id' => $this->assessmentId,
            'organization_id' => $this->organizationId,
        ];
    }
}
