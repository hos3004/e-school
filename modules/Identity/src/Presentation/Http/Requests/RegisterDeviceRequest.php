<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'string', 'max:32'],
            'push_token' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_name.max' => __('identity::validation.device_name_too_long'),
            'push_token.max' => __('identity::validation.push_token_too_long'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'device_name' => __('identity::labels.device_name'),
            'platform' => __('identity::labels.platform'),
            'push_token' => __('identity::labels.push_token'),
        ];
    }
}
