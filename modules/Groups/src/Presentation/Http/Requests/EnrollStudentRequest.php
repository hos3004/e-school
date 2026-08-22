<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Groups\Domain\Models\Group;

/**
 * طلب تسجيل طالب في مجموعة.
 */
final class EnrollStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('enrollStudent', $this->route('group'));
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
    public function attributes(): array
    {
        return [
            'student_profile_id' => __('groups::attributes.student_profile_id'),
        ];
    }
}
