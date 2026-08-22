<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * طلب أرشفة طالب — السبب إلزامي وفق قاعدة التدقيق.
 */
final class ArchiveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return $this->user()->can('delete', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('students::validation.reason_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('students::attributes.reason'),
        ];
    }
}
