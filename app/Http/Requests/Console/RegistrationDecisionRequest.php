<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegistrationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('student.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $accepting = $this->input('decision') === 'accept';
        $newAccount = $accepting && $this->input('account_mode') === 'new';

        return [
            'organization_id' => ['prohibited'], 'actor_id' => ['prohibited'],
            'decision' => ['required', Rule::in(['accept', 'review', 'reject'])],
            'account_mode' => [$accepting ? 'required' : 'nullable', Rule::in(['new', 'existing'])],
            'existing_user_id' => [$accepting && !$newAccount ? 'required' : 'nullable', 'ulid'],
            'username' => [$newAccount ? 'required' : 'nullable', 'string', 'min:'.config('admission.username.min_length'), 'max:'.config('admission.username.max_length')],
            'password' => [$newAccount ? 'required' : 'nullable', Password::defaults(), 'confirmed'],
            'timezone' => [$accepting ? 'required' : 'nullable', 'timezone:all'],
            'identity_confirmed' => ['sometimes', 'boolean'],
            'rejection_category' => [$this->input('decision') === 'reject' ? 'required' : 'nullable', Rule::in(['eligibility', 'schedule', 'duplicate', 'other'])],
            'note' => ['nullable', 'string', 'max:1500', 'required_if:rejection_category,other'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'username' => __('console_registration.fields.username'), 'password' => __('console_registration.fields.password'),
            'timezone' => __('console_registration.fields.timezone'), 'existing_user_id' => __('console_registration.fields.account'),
            'note' => __('console_registration.fields.note'), 'rejection_category' => __('console_registration.fields.rejection_category'),
        ];
    }
}
