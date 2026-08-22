<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Domain\Models\CourseMaterial;

/**
 * تمثيل المادة التعليمية في الـ API.
 *
 * @mixin CourseMaterial
 */
final class CourseMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'disk' => $this->disk,
            'path' => $this->path,
            'external_url' => $this->external_url,
            'size_bytes' => $this->size_bytes,
            'visible_from' => $this->visible_from?->toIso8601String(),
            'visible_to' => $this->visible_to?->toIso8601String(),
            'uploaded_by' => $this->uploaded_by,
            'is_currently_visible' => $this->isCurrentlyVisible(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
