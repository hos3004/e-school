<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Shared\Support\Locales;

final class PeopleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('kind') === 'students' ? 'student.create' : 'staff.contract.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $new = $this->input('account_mode') === 'new';
        $student = $this->route('kind') === 'students';
        $required = $new ? 'required' : 'nullable';
        $rules = [
            'account_mode' => ['required', Rule::in(['new', 'existing'])],
            'existing_user_id' => [$new ? 'nullable' : 'required', 'ulid'],
            'full_name' => [$required, 'string', 'max:255'],
            'username' => [$required, 'string', 'min:'.config('admission.username.min_length'), 'max:'.config('admission.username.max_length')],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$required, Password::defaults(), 'confirmed'],
            'locale' => ['required', Rule::in(Locales::supported())],
            'timezone' => ['required', 'timezone:all'],
            'date_of_birth' => [$student ? 'required' : 'nullable', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'country_id' => ['required', 'ulid'],
            'region_id' => ['required', 'ulid'],
        ];
        // The action validates confirmation too; keep it in the validated payload without ever flashing it.
        $rules['password_confirmation'] = [$required, 'string', 'same:password'];
        if ($new) {
            $rules['email'][] = 'required_without:phone';
            $rules['phone'][] = 'required_without:email';
        }

        return $student ? [...$rules,
            'preferred_program_id' => ['required', 'ulid'],
            'preferred_course_id' => ['required', 'ulid'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['nullable', Rule::in(Locales::supported())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] : [...$rules,
            'staff_code' => ['required', 'string', 'max:32'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'hired_at' => ['required', 'date', ...($this->filled('date_of_birth') ? ['after:date_of_birth'] : [])],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
            'contract_basis' => ['required', Rule::enum(ContractBasis::class)],
            'contract_effective_from' => ['required', 'date'],
            'contract_effective_to' => ['nullable', 'date', 'after:contract_effective_from'],
            'currency' => ['required', Rule::in((array) config('staff.currency.supported'))],
            'base_amount_major' => ['nullable', 'decimal:0,2', 'min:0'],
            'default_rate_major' => ['nullable', 'decimal:0,2', 'gt:0'],
            'monthly_target_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_admin_tasks' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'target_training_sessions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['ulid', 'distinct'],
            'qualification_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
