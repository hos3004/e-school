<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assessments\Domain\Models\Assessment;

/**
 * تمثيل الاختبار في الـ API.
 *
 * @mixin Assessment
 */
final class AssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'course_id' => $this->course_id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'title' => $this->title,
            'instructions' => $this->instructions,
            'total_score' => $this->total_score,
            'passing_score' => $this->passing_score,
            'duration_minutes' => $this->duration_minutes,
            'max_attempts' => $this->max_attempts,
            'available_from' => $this->available_from?->toIso8601String(),
            'available_to' => $this->available_to?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
