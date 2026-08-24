<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.roles.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'guard_name' => ['sometimes', 'string', 'in:web,api'],
            'organization_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('accesscontrol::validation.role_name_required'),
            'organization_id.prohibited' => __('accesscontrol::validation.organization_managed_by_server'),
            'guard_name.in' => __('accesscontrol::validation.guard_invalid'),
        ];
    }
}
