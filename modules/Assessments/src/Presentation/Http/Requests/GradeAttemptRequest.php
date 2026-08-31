<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تصحيح محاولة وإعلان النتيجة.
 */
final class GradeAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assessment.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:'.(int) config('assessments.reason_max_length', 1000)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.required' => __('assessments::validation.score_required'),
            'score.integer' => __('assessments::validation.score_integer'),
            'score.min' => __('assessments::validation.score_min'),
        ];
    }
}
