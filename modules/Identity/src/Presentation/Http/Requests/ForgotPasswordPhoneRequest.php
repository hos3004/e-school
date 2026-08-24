<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'ulid'],
            'phone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
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
