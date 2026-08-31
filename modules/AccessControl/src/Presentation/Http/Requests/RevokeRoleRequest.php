<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.assignments.revoke_role');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'string', 'size:26'],
            'model_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_id.required' => __('accesscontrol::validation.role_required'),
            'model_id.required' => __('accesscontrol::validation.model_id_required'),
            'reason.required' => __('accesscontrol::validation.reason_required'),
        ];
    }
}
