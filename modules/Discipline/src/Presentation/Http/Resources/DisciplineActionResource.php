<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل قيدة إجراء انضباط في الـ API — قراءة فقط.
 *
 * @property-read \Modules\Discipline\Domain\Models\DisciplineAction $resource
 */
final class DisciplineActionResource extends JsonResource
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
            'triggered_by_event_id' => $this->resource->triggered_by_event_id,
            'action' => $this->resource->action?->value,
            'action_label' => $this->resource->action?->label(),
            'threshold_reached' => (int) $this->resource->threshold_reached,
            'window_key' => $this->resource->window_key,
            'is_automatic' => (bool) $this->resource->is_automatic,
            'applied_at' => $this->resource->applied_at?->toIso8601String(),
            'applied_by' => $this->resource->applied_by,
            'notes' => $this->resource->notes,
        ];
    }
}
