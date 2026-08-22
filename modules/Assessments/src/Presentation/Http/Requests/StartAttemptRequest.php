<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب بدء محاولة اختبار — يحدد ملف الطالب الذي تُسجَّل المحاولة عليه.
 */
final class StartAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assessments.attempt.start');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_profile_id.required' => __('assessments::validation.student_profile_required'),
            'student_profile_id.size' => __('assessments::validation.student_profile_invalid'),
        ];
    }
}
