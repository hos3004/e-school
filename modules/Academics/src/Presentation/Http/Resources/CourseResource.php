<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academics\Domain\Models\Course;

/**
 * تمثيل كورس في الـ API — قراءة فقط.
 *
 * @property-read Course $resource
 */
final class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'organization_id' => $this->resource->organization_id,
            'level_id' => $this->resource->level_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'total_sessions' => $this->resource->total_sessions,
            'completion_rules' => $this->resource->completion_rules,
            'is_active' => $this->resource->is_active,
            'archived_at' => $this->resource->deleted_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
