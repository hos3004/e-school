<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignStudentToGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('enrollment.create')
            && $user->can('group.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'ulid'],
            'program_id' => ['required', 'ulid'],
            'course_id' => ['required', 'ulid'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
