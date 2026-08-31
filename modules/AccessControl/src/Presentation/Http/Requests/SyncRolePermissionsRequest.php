<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.roles.sync_permissions');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'max:191'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.present' => __('accesscontrol::validation.permissions_required'),
            'permissions.array' => __('accesscontrol::validation.permissions_required'),
            'permissions.*.required' => __('accesscontrol::validation.permission_name_required'),
            'reason.required' => __('accesscontrol::validation.reason_required'),
        ];
    }
}
