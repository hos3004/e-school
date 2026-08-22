<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;

final class StoreStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || !$user->can('create', StaffProfile::class)) {
            return false;
        }

        $userOrganizationId = data_get($user, 'organization_id');
        $requestedOrganizationId = $this->input('organization_id');
        $targetUserId = $this->input('user_id');

        if (!is_string($userOrganizationId)
            || !is_string($requestedOrganizationId)
            || !is_string($targetUserId)
            || !hash_equals($userOrganizationId, $requestedOrganizationId)) {
            return false;
        }

        /** @var UserQueryService $users */
        $users = app(UserQueryService::class);
        $targetUser = $users->findSummary($targetUserId);

        return $targetUser !== null
            && hash_equals($userOrganizationId, $targetUser->organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'user_id' => ['required', 'string', 'size:26'],
            'staff_code' => ['required', 'string', 'max:32', Rule::unique('staff_profiles', 'staff_code')],
            'employment_type' => ['required', 'string', Rule::enum(EmploymentType::class)],
            'gender' => ['required', 'string', Rule::enum(StaffGender::class)],
            'country_id' => ['required', 'string', 'size:26'],
            'region_id' => ['required', 'string', 'size:26'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:32'],
            'hired_at' => ['nullable', 'date'],
            'bio' => ['nullable', 'array'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $countryId = $this->input('country_id');
            $regionId = $this->input('region_id');

            if (!is_string($countryId) || strlen($countryId) !== 26
                || !is_string($regionId) || strlen($regionId) !== 26) {
                return;
            }

            /** @var GeographyQueries $geography */
            $geography = app(GeographyQueries::class);

            $activeCountryIds = array_map(
                static fn ($country): string => $country->id,
                $geography->countries(),
            );

            if (!in_array($countryId, $activeCountryIds, true)) {
                $validator->errors()->add('country_id', __('staff::validation.country_invalid'));

                return;
            }

            $activeRegionIds = array_map(
                static fn ($region): string => $region->id,
                $geography->regionsOf($countryId),
            );

            if (!in_array($regionId, $activeRegionIds, true)
                || !$geography->regionExistsIn($regionId, $countryId)) {
                $validator->errors()->add('region_id', __('staff::validation.region_country_mismatch'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => __('staff::validation.user_id_required'),
            'user_id.size' => __('staff::validation.ulid'),
            'staff_code.required' => __('staff::validation.staff_code_required'),
            'staff_code.unique' => __('staff::validation.staff_code_unique'),
            'employment_type.required' => __('staff::validation.employment_type_required'),
            'employment_type.Illuminate\\Validation\\Rules\\Enum' => __('staff::validation.employment_type_invalid'),
            'gender.required' => __('staff::validation.gender_required'),
            'gender.Illuminate\\Validation\\Rules\\Enum' => __('staff::validation.gender_invalid'),
            'country_id.required' => __('staff::validation.country_required'),
            'country_id.size' => __('staff::validation.ulid'),
            'region_id.required' => __('staff::validation.region_required'),
            'region_id.size' => __('staff::validation.ulid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => __('staff::validation.attributes.user_id'),
            'staff_code' => __('staff::validation.attributes.staff_code'),
            'employment_type' => __('staff::validation.attributes.employment_type'),
            'gender' => __('staff::validation.attributes.gender'),
            'country_id' => __('staff::validation.attributes.country'),
            'region_id' => __('staff::validation.attributes.region'),
            'date_of_birth' => __('staff::validation.attributes.date_of_birth'),
            'phone' => __('staff::validation.attributes.phone'),
        ];
    }
}
