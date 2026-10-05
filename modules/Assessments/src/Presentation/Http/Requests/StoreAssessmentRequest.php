<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assessments\Domain\Enums\AssessmentType;

/**
 * طلب إنشاء اختبار جديد.
 */
final class StoreAssessmentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(AssessmentType::class)],
            'course_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'array'],
            'title.ar' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'array'],
            'total_score' => ['required', 'integer', 'min:1'],
            'passing_score' => ['required', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'available_from' => ['required', 'date'],
            'available_to' => ['required', 'date', 'after:available_from'],
            'reason' => ['required', 'string', 'max:'.(int) config('assessments.reason_max_length', 1000)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('assessments::validation.type_required'),
            'type.Illuminate\\Validation\\Rules\\Enum' => __('assessments::validation.type_invalid'),
            'title.required' => __('assessments::validation.title_required'),
            'title.ar.required' => __('assessments::validation.title_ar_required'),
            'total_score.required' => __('assessments::validation.total_score_required'),
            'total_score.min' => __('assessments::validation.total_score_min'),
            'passing_score.required' => __('assessments::validation.passing_score_required'),
            'max_attempts.min' => __('assessments::validation.max_attempts_min'),
            'available_from.required' => __('assessments::validation.available_from_required'),
            'available_to.after' => __('assessments::errors.invalid_availability_window'),
        ];
    }
}
