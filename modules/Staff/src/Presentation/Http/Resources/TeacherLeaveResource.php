<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Staff\Domain\Models\TeacherLeave;

/**
 * @property-read TeacherLeave $resource
 */
final class TeacherLeaveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'staff_profile_id' => $this->resource->staff_profile_id,
            'starts_at' => $this->resource->starts_at->toIso8601String(),
            'ends_at' => $this->resource->ends_at->toIso8601String(),
            'reason' => $this->resource->reason,
            'status' => $this->resource->status?->value,
            'approved_by' => $this->resource->approved_by,
            'approved_at' => $this->resource->approved_at?->toIso8601String(),
        ];
    }
}
