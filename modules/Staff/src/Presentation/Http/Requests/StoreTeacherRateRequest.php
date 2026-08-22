<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Staff\Domain\Enums\RateScope;

final class StoreTeacherRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.rate.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scope = RateScope::tryFrom($this->string('scope')->toString());

        return [
            'scope' => ['required', 'string', Rule::enum(RateScope::class)],
            'program_id' => [
                Rule::requiredIf(fn (): bool => $scope !== null && $scope->requiresProgram()),
                'nullable',
                'string',
                'size:26',
            ],
            'course_id' => [
                Rule::requiredIf(fn (): bool => $scope !== null && $scope->requiresCourse()),
                'nullable',
                'string',
                'size:26',
            ],
            'session_type' => ['nullable', 'string', 'max:32'],
            // المبلغ بوحدة رئيسية — يُحوَّل لقروش داخل الإجراء.
            'amount_major' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => __('staff::validation.scope_required'),
            'amount_major.required' => __('staff::validation.amount_required'),
            'amount_major.min' => __('staff::validation.amount_invalid'),
            'effective_to.after' => __('staff::validation.contract_period_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scope' => __('staff::validation.attributes.scope'),
            'amount_major' => __('staff::validation.attributes.amount'),
            'effective_from' => __('staff::validation.attributes.effective_from'),
            'effective_to' => __('staff::validation.attributes.effective_to'),
        ];
    }
}
