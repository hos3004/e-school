<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Staff\Domain\Enums\EmploymentType;
use Shared\Support\Locales;

final class PeopleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('kind') === 'students' ? 'student.update' : 'staff.contract.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'country_id' => ['required', 'ulid'],
            'region_id' => ['required', 'ulid'],
        ];

        return $this->route('kind') === 'students' ? [...$rules,
            'nationality' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['nullable', Rule::in(Locales::supported())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] : [...$rules,
            'staff_code' => ['required', 'string', 'max:32'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'hired_at' => ['nullable', 'date', ...($this->filled('date_of_birth') ? ['after:date_of_birth'] : [])],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
        ];
    }
}
