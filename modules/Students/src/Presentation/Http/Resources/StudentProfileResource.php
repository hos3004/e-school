<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * تمثيل ملف الطالب في الـ API — قراءة فقط.
 *
 * @property-read StudentProfile $resource
 */
final class StudentProfileResource extends JsonResource
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
            'student_code' => $this->resource->student_code,
            'date_of_birth' => $this->resource->date_of_birth?->toDateString(),
            'gender' => $this->resource->gender?->value,
            'gender_label' => $this->resource->gender?->label(),
            'nationality' => $this->resource->nationality,
            'country' => $this->resource->country,
            'city' => $this->resource->city,
            'preferred_language' => $this->resource->preferred_language,
            'joined_at' => $this->resource->joined_at?->toDateString(),
            'notes' => $this->resource->notes,
            'archived_at' => $this->resource->deleted_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
