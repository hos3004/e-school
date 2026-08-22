<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Staff\Domain\Models\StaffProfile;

/**
 * @property-read StaffProfile $resource
 */
final class StaffProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'organization_id' => $this->resource->organization_id,
            'user_id' => $this->resource->user_id,
            'staff_code' => $this->resource->staff_code,
            'employment_type' => $this->resource->employment_type?->value,
            'gender' => $this->resource->gender?->value,
            'country_id' => $this->resource->country_id,
            'region_id' => $this->resource->region_id,
            'date_of_birth' => $this->resource->date_of_birth?->toDateString(),
            'phone' => $this->resource->phone,
            'hired_at' => $this->resource->hired_at?->toDateString(),
            'terminated_at' => $this->resource->terminated_at?->toDateString(),
            'is_active' => $this->resource->isActive(),
            'bio' => $this->resource->bio,
            'specializations' => $this->resource->specializations,
        ];
    }
}
