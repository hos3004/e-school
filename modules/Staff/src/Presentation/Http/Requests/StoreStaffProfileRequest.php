<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Staff\Domain\Enums\EmploymentType;

final class StoreStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.profile.create') ?? false;
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
            'hired_at' => ['nullable', 'date'],
            'bio' => ['nullable', 'array'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
        ];
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
        ];
    }
}
