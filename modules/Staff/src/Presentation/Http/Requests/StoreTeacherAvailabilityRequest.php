<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Staff\Domain\Models\StaffProfile;

final class StoreTeacherAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || !$user->can('staff.availability.create')) {
            return false;
        }

        $organizationId = data_get($user, 'organization_id');
        $staffProfileId = $this->input('staff_profile_id');

        if (!is_string($organizationId) || !is_string($staffProfileId)) {
            return false;
        }

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()
            ->whereKey($staffProfileId)
            ->where('organization_id', $organizationId)
            ->first();

        if ($profile === null) {
            return false;
        }

        return (string) $profile->user_id === (string) $user->getAuthIdentifier()
            || $user->can('staff.view.any');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weekday.between' => __('staff::validation.weekday_invalid'),
            'end_time.after' => __('staff::validation.availability_time_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'staff_profile_id' => __('staff::validation.attributes.staff_profile'),
            'weekday' => __('staff::validation.attributes.weekday'),
            'start_time' => __('staff::validation.attributes.start_time'),
            'end_time' => __('staff::validation.attributes.end_time'),
            'effective_from' => __('staff::validation.attributes.effective_from'),
            'effective_to' => __('staff::validation.attributes.effective_to'),
        ];
    }
}
