<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payroll\Domain\Models\PayrollPeriod;

/**
 * تمثيل فترة مستحقات في الـ API.
 *
 * @mixin PayrollPeriod
 */
final class PayrollPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'year' => $this->year,
            'month' => $this->month,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'totals' => $this->totals,
        ];
    }
}
