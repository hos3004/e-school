<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('accesscontrol.roles.delete');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => __('accesscontrol::validation.reason_required'),
        ];
    }
}
