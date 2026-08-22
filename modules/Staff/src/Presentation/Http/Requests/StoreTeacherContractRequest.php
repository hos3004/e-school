<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Staff\Domain\Enums\ContractBasis;

final class StoreTeacherContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.contract.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'basis' => ['required', 'string', Rule::enum(ContractBasis::class)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            // المبلغ بوحدة رئيسية من الإدخال البشري — يُحوَّل لقروش داخل الإجراء.
            'base_amount_major' => [
                'nullable',
                'numeric',
                Rule::requiredIf(fn (): bool => in_array($this->string('basis')->toString(), [ContractBasis::Salary->value, ContractBasis::Hybrid->value], true)),
                Rule::prohibitedIf(fn (): bool => $this->string('basis')->toString() === ContractBasis::PerSession->value),
                'min:0',
                'decimal:0,2',
            ],
            'monthly_target_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_admin_tasks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_training_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'terms' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'staff_profile_id.required' => __('staff::validation.staff_profile_required'),
            'basis.required' => __('staff::validation.basis_required'),
            'effective_to.after' => __('staff::validation.contract_period_invalid'),
            'base_amount_major.decimal' => __('staff::validation.amount_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'staff_profile_id' => __('staff::validation.attributes.staff_profile'),
            'basis' => __('staff::validation.attributes.basis'),
            'effective_from' => __('staff::validation.attributes.effective_from'),
            'effective_to' => __('staff::validation.attributes.effective_to'),
            'base_amount_major' => __('staff::validation.attributes.base_amount'),
        ];
    }
}
