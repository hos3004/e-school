<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Shared\ValueObjects\Money;

/**
 * @property-read \Modules\Staff\Domain\Models\TeacherContract $resource
 */
final class TeacherContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = $this->resource->baseMoney();

        return [
            'id' => $this->resource->id,
            'organization_id' => $this->resource->organization_id,
            'staff_profile_id' => $this->resource->staff_profile_id,
            'basis' => $this->resource->basis?->value,
            'effective_from' => $this->resource->effective_from->toDateString(),
            'effective_to' => $this->resource->effective_to?->toDateString(),
            'base_amount_minor' => $base?->minorUnits,
            'currency' => $base?->currency,
            'monthly_target_sessions' => $this->resource->monthly_target_sessions,
            'target_admin_tasks' => $this->resource->target_admin_tasks,
            'target_training_sessions' => $this->resource->target_training_sessions,
            'terms' => $this->resource->terms,
        ];
    }
}
