<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب تصحيح يدوي موثّق لعدّاد في لوحة طالب.
 */
final class CorrectStudentDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reporting.student.correct');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'string', 'size:26'],
            'column' => ['required', 'string', Rule::in([
                'sessions_total',
                'sessions_attended',
                'sessions_missed',
                'violations_count',
                'freezes_count',
            ])],
            'value' => ['required', 'integer', 'min:0'],
            'reason' => [
                'required',
                'string',
                'min:'.(int) config('reporting.correction.reason_min_chars', 5),
                'max:'.(int) config('reporting.correction.reason_max_chars', 500),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('reporting::errors.correction_reason_required'),
            'reason.min' => __('reporting::errors.correction_reason_length', [
                'min' => (int) config('reporting.correction.reason_min_chars', 5),
                'max' => (int) config('reporting.correction.reason_max_chars', 500),
            ]),
            'value.min' => __('reporting::errors.negative_counter_value', [
                'value' => $this->integer('value'),
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'enrollment_id' => __('reporting::fields.enrollment'),
            'column' => __('reporting::fields.column'),
            'value' => __('reporting::fields.value'),
            'reason' => __('reporting::fields.reason'),
        ];
    }
}
