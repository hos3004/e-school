<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;

/**
 * طلب إسناد معلم إلى مجموعة.
 */
final class AssignTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignTeacher', $this->route('group'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'course_id' => ['nullable', 'string', 'size:26'],
            'role' => ['required', 'string', Rule::enum(GroupTeacherRole::class)],
            'assigned_from' => ['required', 'date'],
            'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.after_or_equal' => __('groups::validation.ends_before_starts'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'staff_profile_id',
            'course_id',
            'role',
            'assigned_from',
            'assigned_to',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('groups::attributes.'.$field)],
        )->all();
    }
}
