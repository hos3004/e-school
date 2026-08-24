<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Students\Domain\Models\RegistrationApplication;

/** @property-read RegistrationApplication $resource */
final class RegistrationApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'organization_id' => $this->resource->organization_id,
            'user_id' => $this->resource->user_id,
            'student_profile_id' => $this->resource->student_profile_id,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'full_name' => $this->resource->full_name,
            'date_of_birth' => $this->resource->date_of_birth->toDateString(),
            'gender' => $this->resource->gender->value,
            'country_id' => $this->resource->country_id,
            'region_id' => $this->resource->region_id,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'preferred_program_id' => $this->resource->preferred_program_id,
            'preferred_course_id' => $this->resource->preferred_course_id,
            'notes' => $this->resource->notes,
            'submitted_at' => $this->resource->submitted_at?->toIso8601String(),
            'reviewed_by' => $this->resource->reviewed_by,
            'reviewed_at' => $this->resource->reviewed_at?->toIso8601String(),
            'decision_reason' => $this->resource->decision_reason,
            'duplicate_of_application_id' => $this->resource->duplicate_of_application_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
