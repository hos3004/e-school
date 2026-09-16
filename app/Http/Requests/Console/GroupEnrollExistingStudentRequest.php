<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

final class GroupEnrollExistingStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('student.view.any') && $this->user()->can('enrollment.create') && $this->user()->can('group.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'ulid'],
            'course_id' => ['required', 'ulid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'student_profile_id' => __('console_registration.placement.existing_student'),
            'course_id' => __('console_registration.fields.course'),
        ];
    }
}
