<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Organization\Domain\Models\Organization;

final class UpdateOrganizationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        return $organization !== null
            && (bool) $this->user()?->can('manageSettings', $organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:'.(int) config('organization.limits.setting_key_max_length')],
            'value' => ['present'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => __('organization::validation.setting_key_required'),
            'key.max' => __('organization::validation.setting_key_too_long'),
            'value.present' => __('organization::validation.setting_value_required'),
        ];
    }
}
