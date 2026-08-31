<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeModelPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.permissions.grant_direct');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permission' => ['required', 'string', 'max:191'],
            'model_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permission.required' => __('accesscontrol::validation.permission_name_required'),
            'model_id.required' => __('accesscontrol::validation.model_id_required'),
            'reason.required' => __('accesscontrol::validation.reason_required'),
        ];
    }
}
