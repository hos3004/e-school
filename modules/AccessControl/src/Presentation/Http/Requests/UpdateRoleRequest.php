<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.roles.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'organization_id' => ['prohibited'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization_id.prohibited' => __('accesscontrol::validation.organization_managed_by_server'),
            'reason.required' => __('accesscontrol::validation.reason_required'),
        ];
    }
}
