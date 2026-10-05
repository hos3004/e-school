<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessments\Domain\Models\AssessmentAttempt;

/**
 * تمثيل محاولة اختبار في الـ API.
 *
 * @mixin AssessmentAttempt
 */
final class AssessmentAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'student_profile_id' => $this->student_profile_id,
            'attempt_number' => $this->attempt_number,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'score' => $this->score,
            'passed' => $this->passed,
            'graded_by' => $this->graded_by,
            'graded_at' => $this->graded_at?->toIso8601String(),
            // الإجابات تُخفى بعد التسليم عمن لا يملك صلاحية إدارة التصحيح.
            'answers' => $this->when(
                $request->user()?->can('assessment.manage'),
                $this->answers,
            ),
        ];
    }
}
