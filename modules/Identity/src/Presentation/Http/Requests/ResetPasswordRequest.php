<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:191'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('identity::validation.email_required'),
            'email.email' => __('identity::validation.email_invalid'),
            'token.required' => __('identity::validation.reset_token_required'),
            'password.required' => __('identity::validation.password_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('identity::labels.email'),
            'token' => __('identity::labels.reset_token'),
            'password' => __('identity::labels.password'),
        ];
    }
}
