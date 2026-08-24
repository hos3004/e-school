<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationApplication;

final class StoreRegistrationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', RegistrationApplication::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(StudentGender::class)],
            'country_id' => ['required', 'string', 'size:26'],
            'region_id' => ['required', 'string', 'size:26'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'preferred_program_id' => ['nullable', 'ulid'],
            'preferred_course_id' => ['nullable', 'ulid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            $this->validateGeography(...),
            $this->validateMinimumSelfRegistrationAge(...),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $field): array => [$field => __('students::attributes.'.$field)])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $phone = $this->input('phone');

        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'email' => is_string($email) && trim($email) !== '' ? mb_strtolower(trim($email)) : null,
            'phone' => is_string($phone) && trim($phone) !== '' ? trim($phone) : null,
        ]);
    }

    private function validateGeography(Validator $validator): void
    {
        $countryId = (string) $this->input('country_id');
        $regionId = (string) $this->input('region_id');

        if ($countryId === '' || $regionId === '') {
            return;
        }

        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $countryExists = collect($geography->countries())
            ->contains(static fn (CountryData $country): bool => $country->id === $countryId);

        if (!$countryExists) {
            $validator->errors()->add('country_id', __('students::validation.country_invalid'));

            return;
        }

        if (!$geography->regionExistsIn($regionId, $countryId)) {
            $validator->errors()->add('region_id', __('students::validation.region_not_in_country'));
        }
    }

    private function validateMinimumSelfRegistrationAge(Validator $validator): void
    {
        if ($validator->errors()->has('date_of_birth')) {
            return;
        }

        $minimumAge = (int) config('admission.self_registration.min_self_registration_age', 0);
        $dateOfBirth = $this->input('date_of_birth');

        if ($minimumAge <= 0 || !is_string($dateOfBirth)) {
            return;
        }

        try {
            $eligibleFrom = CarbonImmutable::parse($dateOfBirth)
                ->startOfDay()
                ->addYears($minimumAge);
        } catch (\Throwable) {
            return;
        }

        if ($eligibleFrom->isAfter(CarbonImmutable::today())) {
            $validator->errors()->add(
                'date_of_birth',
                __('students::validation.minimum_self_registration_age', ['age' => $minimumAge]),
            );
        }
    }
}
