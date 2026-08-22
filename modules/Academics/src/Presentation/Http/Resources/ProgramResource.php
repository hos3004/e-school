<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academics\Domain\Models\Program;

/**
 * تمثيل برنامج أكاديمي في الـ API — قراءة فقط.
 *
 * @property-read Program $resource
 */
final class ProgramResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'organization_id' => $this->resource->organization_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'duration_weeks' => $this->resource->duration_weeks,
            'default_session_minutes' => $this->resource->default_session_minutes,
            'default_rate' => $this->resource->default_rate,
            'currency' => $this->resource->currency,
            'is_active' => $this->resource->is_active,
            'sort_order' => $this->resource->sort_order,
            'archived_at' => $this->resource->deleted_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
