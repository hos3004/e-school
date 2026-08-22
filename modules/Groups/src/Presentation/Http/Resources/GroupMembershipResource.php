<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل انتساب طالب في الـ API — قراءة فقط.
 *
 * @property-read \Modules\Groups\Domain\Models\GroupMembership $resource
 */
final class GroupMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'group_id' => $this->resource->group_id,
            'student_profile_id' => $this->resource->student_profile_id,
            'status' => $this->resource->status?->value,
            'status_label' => $this->resource->status?->label(),
            'joined_at' => $this->resource->joined_at?->toIso8601String(),
            'left_at' => $this->resource->left_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
