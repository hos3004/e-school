<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تسليم محاولة — الإجابات خريطة معرّف سؤال ← إجابة الطالب.
 */
final class SubmitAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assessments.attempt.submit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*' => ['present'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => __('assessments::validation.answers_required'),
            'answers.array' => __('assessments::validation.answers_invalid'),
        ];
    }
}
