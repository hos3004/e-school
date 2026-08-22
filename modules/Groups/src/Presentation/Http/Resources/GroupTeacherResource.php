<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Groups\Domain\Models\GroupTeacher;

/**
 * تمثيل إسناد معلم في الـ API — قراءة فقط.
 *
 * @property-read GroupTeacher $resource
 */
final class GroupTeacherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'group_id' => $this->resource->group_id,
            'staff_profile_id' => $this->resource->staff_profile_id,
            'course_id' => $this->resource->course_id,
            'role' => $this->resource->role?->value,
            'role_label' => $this->resource->role?->label(),
            'assigned_from' => $this->resource->assigned_from?->toDateString(),
            'assigned_to' => $this->resource->assigned_to?->toDateString(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
