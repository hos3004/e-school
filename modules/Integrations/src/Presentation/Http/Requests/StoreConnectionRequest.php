<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إنشاء اتصال جديد بمزوّد خارجي.
 */
final class StoreConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('integrations.connection.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'provider_id' => ['required', 'string', 'size:26', 'exists:integration_providers,id'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['required', 'string'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider_id.exists' => __('integrations::validation.provider_exists'),
        ];
    }
}
