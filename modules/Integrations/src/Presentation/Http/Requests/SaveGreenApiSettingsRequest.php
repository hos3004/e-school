<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveGreenApiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->can('settings.manage')
            && (bool) $this->user()->can('integrations.connection.update')
            && is_string(data_get($this->user(), 'organization_id'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'api_url' => ['required', 'string', 'max:255',
                'regex:#^https://(?:api\.green-api\.com|(?:\d+\.)?api\.greenapi\.com)/?$#'],
            'instance_id' => ['required', 'string', 'regex:/^\d+$/', 'max:20'],
            'token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]+$/', 'max:128'],
            'enabled' => ['required', 'boolean'],
            'version' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'api_url' => __('console_settings.green_api.api_url'),
            'instance_id' => __('console_settings.green_api.instance_id'),
            'token' => __('console_settings.green_api.token'),
            'reason' => __('console_settings.green_api.reason'),
        ];
    }
}
