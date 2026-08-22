<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Discipline\Domain\Models\ReactivationRequest;

/**
 * تمثيل طلب إعادة تفعيل في الـ API.
 *
 * @property-read ReactivationRequest $resource
 */
final class ReactivationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'organization_id' => $this->resource->organization_id,
            'enrollment_id' => $this->resource->enrollment_id,
            'requested_by' => $this->resource->requested_by,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'attempt_number' => (int) $this->resource->attempt_number,
            'assessment_attempt_id' => $this->resource->assessment_attempt_id,
            'student_statement' => $this->resource->student_statement,
            'reviewer_id' => $this->resource->reviewer_id,
            'reviewed_at' => $this->resource->reviewed_at?->toIso8601String(),
            'decision_note' => $this->resource->decision_note,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
