<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null || $this->user()->can('identity.users.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')],
            'username' => ['nullable', 'string', 'max:64', Rule::unique('users', 'username')],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'password' => ['required', 'string', Password::defaults()],
            'locale' => ['sometimes', 'string', 'max:8'],
            'timezone' => ['sometimes', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization_id.required' => __('identity::validation.organization_id_required'),
            'name.required' => __('identity::validation.name_required'),
            'email.required' => __('identity::validation.email_required'),
            'email.email' => __('identity::validation.email_invalid'),
            'email.unique' => __('identity::errors.email_taken'),
            'username.unique' => __('identity::errors.username_taken'),
            'password.required' => __('identity::validation.password_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('identity::labels.organization'),
            'name' => __('identity::labels.name'),
            'email' => __('identity::labels.email'),
            'username' => __('identity::labels.username'),
            'phone' => __('identity::labels.phone'),
            'password' => __('identity::labels.password'),
        ];
    }
}
