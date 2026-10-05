<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'ulid'],
            'phone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
            'otp' => [
                'required',
                'string',
                'digits:'.(int) config('identity.phone_password_reset.otp_digits'),
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'organization_id.required' => __('identity::validation.organization_id_required'),
            'organization_id.ulid' => __('identity::validation.organization_id_invalid'),
            'phone.required' => __('identity::validation.phone_required'),
            'phone.regex' => __('identity::validation.phone_invalid'),
            'otp.required' => __('identity::validation.otp_required'),
            'otp.digits' => __('identity::validation.otp_invalid'),
            'password.required' => __('identity::validation.password_required'),
            'password.confirmed' => __('identity::validation.password_confirmation_mismatch'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge(['phone' => preg_replace('/[\s().-]+/', '', trim($phone))]);
        }
    }
}
