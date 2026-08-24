<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Students\Domain\Contracts\RegistrationOfferingQueries;
use Modules\Students\Domain\Enums\StudentGender;

final class PublicRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('admission.self_registration.enabled', false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'],
            'course_id' => ['prohibited'],
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(StudentGender::class)],
            'country_id' => ['required', 'ulid'],
            'region_id' => ['required', 'ulid'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:32', 'required_without:email'],
            'preferred_program_id' => ['required', 'ulid'],
            'preferred_course_id' => ['required', 'ulid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            $this->validateGeography(...),
            $this->validateMinimumAge(...),
            $this->validateOffering(...),
        ];
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
        if ($validator->errors()->hasAny(['country_id', 'region_id'])) {
            return;
        }

        $countryId = (string) $this->input('country_id');
        $regionId = (string) $this->input('region_id');
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

    private function validateMinimumAge(Validator $validator): void
    {
        if ($validator->errors()->has('date_of_birth')) {
            return;
        }

        $minimumAge = (int) config('admission.self_registration.min_self_registration_age', 0);
        $dateOfBirth = $this->input('date_of_birth');

        if ($minimumAge <= 0 || !is_string($dateOfBirth)) {
            return;
        }

        if (CarbonImmutable::parse($dateOfBirth)->addYears($minimumAge)->isAfter(CarbonImmutable::today())) {
            $validator->errors()->add(
                'date_of_birth',
                __('students::validation.minimum_self_registration_age', ['age' => $minimumAge]),
            );
        }
    }

    private function validateOffering(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['preferred_program_id', 'preferred_course_id'])) {
            return;
        }

        $organizationId = (string) $this->route('organizationId');
        /** @var RegistrationOfferingQueries $offerings */
        $offerings = app(RegistrationOfferingQueries::class);

        if (!$offerings->isAvailable(
            $organizationId,
            (string) $this->input('preferred_program_id'),
            (string) $this->input('preferred_course_id'),
        )) {
            $validator->errors()->add(
                'preferred_course_id',
                __('students::validation.registration_offering_invalid'),
            );
        }
    }
}
