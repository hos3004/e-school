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
        return $this->user()?->can('assessment.take') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
