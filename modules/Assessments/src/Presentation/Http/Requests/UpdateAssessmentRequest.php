<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assessments\Domain\Enums\AssessmentType;

/**
 * طلب تعديل اختبار قائم — كل الحقول اختيارية، والمُرسَل فقط يُحدَّث.
 */
final class UpdateAssessmentRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(AssessmentType::class)],
            'course_id' => ['nullable', 'string', 'size:26'],
            'title' => ['sometimes', 'array'],
            'title.ar' => ['required_with:title', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'array'],
            'total_score' => ['sometimes', 'integer', 'min:1'],
            'passing_score' => ['sometimes', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
            'available_from' => ['sometimes', 'date'],
            'available_to' => ['sometimes', 'date', 'after:available_from'],
            'reason' => ['required', 'string', 'max:'.(int) config('assessments.reason_max_length', 1000)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.*' => __('assessments::validation.type_invalid'),
            'title.ar.required_with' => __('assessments::validation.title_ar_required'),
            'available_to.after' => __('assessments::errors.invalid_availability_window'),
        ];
    }
}
