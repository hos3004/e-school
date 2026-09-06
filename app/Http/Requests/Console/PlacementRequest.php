<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

final class PlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('student.view.any') && $this->user()->can('enrollment.create') && $this->user()->can('group.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'program_id' => ['prohibited'],
            'application_ids' => ['required', 'array', 'min:1', 'max:'.config('console.directory_per_page')],
            'application_ids.*' => ['required', 'ulid', 'distinct'],
            'course_id' => ['required', 'ulid'],
            'group_id' => ['nullable', 'required_without:new_group_name', 'prohibits:new_group_name', 'ulid'],
            'new_group_name' => ['nullable', 'required_without:group_id', 'prohibits:group_id', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'application_ids' => __('console_registration.placement.students'),
            'course_id' => __('console_registration.fields.course'),
            'group_id' => __('console_registration.placement.group'),
            'new_group_name' => __('console_registration.placement.new_group_name'),
        ];
    }
}
