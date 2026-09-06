<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Modules\Groups\Domain\Enums\GroupTeacherRole;

final class GroupSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('group.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('console.groups.activate')) {
            return ['organization_id' => ['prohibited']];
        }
        if ($this->routeIs('console.groups.teachers')) {
            return [
                'organization_id' => ['prohibited'],
                'staff_profile_id' => ['required', 'string', 'size:26'],
                'course_id' => ['required', 'string', 'size:26'],
                'role' => ['required', Rule::enum(GroupTeacherRole::class)],
                'assigned_from' => ['required', 'date'],
                'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
            ];
        }

        return [
            'organization_id' => ['prohibited'],
            'status' => ['prohibited'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('groups', 'code')->ignore($this->route('group'))],
            'name' => ['required', 'array:ar,en,fr'],
            'name.ar' => ['required', 'string', 'max:120'],
            'name.en' => ['nullable', 'string', 'max:120'],
            'name.fr' => ['nullable', 'string', 'max:120'],
            'capacity' => ['nullable', 'integer', 'min:'.config('groups.capacity.minimum'), 'max:'.config('groups.capacity.maximum')],
            'timezone' => ['required', 'timezone:all'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'program_ids' => ['required', 'array', 'min:1'],
            'program_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ];
    }

    /** @return array<string, mixed> */
    public function actionData(): array
    {
        $fields = match (true) {
            $this->routeIs('console.groups.activate') => [],
            $this->routeIs('console.groups.teachers') => [
                'staff_profile_id', 'course_id', 'role', 'assigned_from', 'assigned_to',
            ],
            default => ['code', 'name', 'capacity', 'timezone', 'starts_on', 'ends_on', 'program_ids'],
        };

        return Arr::only($this->validated(), $fields);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(function (string $key): array {
            $label = 'console_courses.fields.'.str_replace('.', '_', $key);

            return [$key => Lang::has($label, app()->getLocale(), false)
                ? __($label)
                : ((array) __('validation.attributes'))[$key] ?? $key];
        })->all();
    }
}
