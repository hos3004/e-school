<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Discipline\Domain\Models\ViolationEvent;

/**
 * تمثيل حدث مخالفة في الـ API — قراءة فقط.
 *
 * @property-read ViolationEvent $resource
 */
final class ViolationEventResource extends JsonResource
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
            'student_profile_id' => $this->resource->student_profile_id,
            'session_id' => $this->resource->session_id,
            'type' => $this->resource->type?->value,
            'type_label' => $this->resource->type?->label(),
            'occurred_at' => $this->resource->occurred_at?->toIso8601String(),
            'window_key' => $this->resource->window_key,
            'is_countable' => (bool) $this->resource->is_countable,
            'waived_by' => $this->resource->waived_by,
            'waived_at' => $this->resource->waived_at?->toIso8601String(),
            'waiver_reason' => $this->resource->waiver_reason,
        ];
    }
}
