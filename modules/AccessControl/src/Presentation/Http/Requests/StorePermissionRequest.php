<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.permissions.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'regex:/^[a-z][a-z0-9_.-]*$/'],
            'guard_name' => ['required', 'string', 'in:web,api'],
            'module' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:1000'],
            'description.en' => ['nullable', 'string', 'max:1000'],
            'description.fr' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('accesscontrol::validation.permission_name_required'),
            'name.regex' => __('accesscontrol::validation.permission_name_format'),
            'guard_name.in' => __('accesscontrol::validation.guard_invalid'),
        ];
    }
}
