<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Staff\Domain\Models\TeacherAvailability;

/**
 * @property-read TeacherAvailability $resource
 */
final class TeacherAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'staff_profile_id' => $this->resource->staff_profile_id,
            'weekday' => $this->resource->weekday,
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            'timezone' => $this->resource->timezone,
            'effective_from' => $this->resource->effective_from->toDateString(),
            'effective_to' => $this->resource->effective_to?->toDateString(),
        ];
    }
}
