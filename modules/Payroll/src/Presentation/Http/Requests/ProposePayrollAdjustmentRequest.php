<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب اقتراح تسوية (مكافأة/خصم/تصحيح) على فترة.
 */
final class ProposePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can((string) config('payroll.adjustments.propose_permission'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $types */
        $types = config('payroll.adjustments.types', []);

        $requiredNote = config('payroll.adjustments.requires_note') === true;

        return [
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'type' => ['required', 'string', Rule::in($types)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => [$requiredNote ? 'required' : 'nullable', 'string', 'min:3', 'max:2000'],
            'references_period_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'staff_profile_id' => __('payroll::fields.staff_profile'),
            'type' => __('payroll::fields.adjustment_type'),
            'amount' => __('payroll::fields.amount'),
            'reason' => __('payroll::fields.reason'),
            'references_period_id' => __('payroll::fields.references_period'),
        ];
    }
}
