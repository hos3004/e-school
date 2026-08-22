<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Staff\Domain\Models\TeacherRate;

/**
 * @property-read TeacherRate $resource
 */
final class TeacherRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'teacher_contract_id' => $this->resource->teacher_contract_id,
            'scope' => $this->resource->scope?->value,
            'program_id' => $this->resource->program_id,
            'course_id' => $this->resource->course_id,
            'session_type' => $this->resource->session_type,
            'amount_minor' => $this->resource->amount,
            'amount_major' => $this->resource->money()->toMajor(),
            'currency' => $this->resource->currency,
            'effective_from' => $this->resource->effective_from->toDateString(),
            'effective_to' => $this->resource->effective_to?->toDateString(),
        ];
    }
}
