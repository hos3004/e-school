<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'string', 'max:32'],
            'push_token' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => __('identity::labels.identifier'),
        ];
    }
}
