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
        $usernameMinLength = (int) config('admission.username.min_length');
        $usernameMaxLength = (int) config('admission.username.max_length');

        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'nullable',
                'required_without:phone',
                'string',
                'email',
                'max:191',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'username' => [
                'required',
                'string',
                'min:'.$usernameMinLength,
                'max:'.$usernameMaxLength,
                Rule::notIn((array) config('admission.username.reserved', [])),
                Rule::unique('users', 'username')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
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
            'email.required_without' => __('identity::validation.contact_required'),
            'email.email' => __('identity::validation.email_invalid'),
            'email.unique' => __('identity::errors.email_taken'),
            'username.required' => __('identity::validation.username_required'),
            'username.not_in' => __('identity::validation.username_reserved'),
            'username.unique' => __('identity::errors.username_taken'),
            'phone.required_without' => __('identity::validation.contact_required'),
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
