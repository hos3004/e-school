<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TeacherDuesDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can((string) config('payroll.adjustments.approve_permission'));
    }

    protected function prepareForValidation(): void
    {
        $category = $this->input('reason_category');
        $note = $this->input('note');
        $this->merge(['reason' => is_string($category) ? trim((string) __('console_dues.reasons.'.$category).(is_string($note) && trim($note) !== '' ? ': '.trim($note) : '')) : '']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason_category' => ['required', Rule::in(['reviewed', 'incomplete', 'not_due', 'other'])],
            'note' => [Rule::requiredIf($this->input('reason_category') === 'other'), 'nullable', 'string', 'max:1500'],
            'reason' => ['required', 'string', 'min:3', 'max:2000']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason_category' => __('console_dues.decision_category'), 'note' => __('console_dues.note')];
    }
}
