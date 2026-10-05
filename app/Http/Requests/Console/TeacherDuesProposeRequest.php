<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TeacherDuesProposeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can((string) config('payroll.adjustments.propose_permission'));
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
        return ['staff_profile_id' => ['required', 'ulid'], 'type' => ['required', Rule::in((array) config('payroll.adjustments.types'))],
            'amount' => ['required', 'string', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'reason_category' => ['required', Rule::in(['extra_work', 'materials', 'periodic', 'previous_error', 'advance', 'reimbursement', 'other'])],
            'note' => [Rule::requiredIf($this->input('reason_category') === 'other'), 'nullable', 'string', 'max:1500'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'], 'references_period_id' => ['nullable', 'ulid']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => __('console_dues.amount'), 'reason_category' => __('console_dues.reason_category'),
            'note' => __('console_dues.note'), 'staff_profile_id' => __('console_dues.teacher'),
            'references_period_id' => __('console_dues.reference_period'), 'type' => __('console_dues.adjustment_type')];
    }
}
