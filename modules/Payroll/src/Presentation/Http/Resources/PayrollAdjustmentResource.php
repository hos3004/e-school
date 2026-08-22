<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Shared\ValueObjects\Money;

/**
 * تمثيل تسوية في الـ API.
 *
 * @mixin PayrollAdjustment
 */
final class PayrollAdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'payroll_period_id' => $this->payroll_period_id,
            'staff_profile_id' => $this->staff_profile_id,
            'type' => $this->type,
            'amount' => [
                'minor_units' => (int) $this->amount,
                'major' => Money::of((int) $this->amount, (string) $this->currency)->toMajor(),
                'currency' => $this->currency,
            ],
            'reason' => $this->reason,
            'references_period_id' => $this->references_period_id,
            'proposed_by' => $this->proposed_by,
            'proposed_at' => $this->proposed_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'is_pending' => $this->approved_at === null && $this->rejected_at === null,
        ];
    }
}
