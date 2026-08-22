<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assessments\Domain\Enums\QuestionType;

/**
 * طلب إضافة سؤال إلى اختبار.
 */
final class AddQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assessments.question.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'array'],
            'body.ar' => ['required', 'string', 'max:2000'],
            'body.en' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array'],
            'options.*.text.ar' => ['required_with:options', 'string', 'max:500'],
            'correct_answer' => ['nullable', 'array'],
            'score' => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('assessments::validation.question_type_required'),
            'body.required' => __('assessments::validation.question_body_required'),
            'body.ar.required' => __('assessments::validation.question_body_ar_required'),
            'score.required' => __('assessments::validation.question_score_required'),
        ];
    }
}
