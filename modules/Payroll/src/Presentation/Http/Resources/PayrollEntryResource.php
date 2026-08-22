<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Shared\ValueObjects\Money;

/**
 * تمثيل قيدة مستحقات في الـ API.
 *
 * @mixin PayrollEntry
 */
final class PayrollEntryResource extends JsonResource
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
            'session_id' => $this->session_id,
            'teacher_contract_id' => $this->teacher_contract_id,
            'entry_type' => $this->entry_type,
            'outcome_key' => $this->outcome_key,
            'amount' => [
                'minor_units' => (int) $this->amount,
                'major' => Money::of((int) $this->amount, (string) $this->currency)->toMajor(),
                'currency' => $this->currency,
            ],
            'rate_snapshot' => $this->rate_snapshot,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'deferred_until_session_id' => $this->deferred_until_session_id,
            'description' => $this->description,
        ];
    }
}
