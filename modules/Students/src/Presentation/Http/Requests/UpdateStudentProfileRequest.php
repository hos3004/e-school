<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\Locales;

/**
 * طلب تحديث بيانات ملف طالب.
 */
final class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StudentProfile $student */
        $student = $this->route('student');

        return $this->user()->can('update', $student);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female'],
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2'],
            'country' => ['prohibited'],
            'country_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'region_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_language' => ['sometimes', 'nullable', 'string', Rule::in(Locales::supported())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [$this->validateGeography(...)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_of_birth.before' => __('students::validation.birth_before_today'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'reason',
            'date_of_birth',
            'gender',
            'nationality',
            'country_id',
            'region_id',
            'city',
            'preferred_language',
            'notes',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('students::attributes.'.$field)],
        )->all();
    }

    private function validateGeography(Validator $validator): void
    {
        $student = $this->route('student');
        $countryId = (string) ($this->input('country_id') ?? data_get($student, 'country_id'));
        $regionId = (string) ($this->input('region_id') ?? data_get($student, 'region_id'));

        if ($countryId === '' && $regionId === '') {
            return;
        }

        if ($countryId === '' || $regionId === '') {
            $validator->errors()->add('region_id', __('students::validation.region_not_in_country'));

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
}
