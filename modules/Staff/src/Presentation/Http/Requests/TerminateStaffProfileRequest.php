<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Staff\Domain\Models\StaffProfile;

final class TerminateStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StaffProfile|null $profile */
        $profile = $this->route('profile');

        if ($profile === null) {
            return false;
        }

        return $this->user()?->can('terminate', $profile) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('staff::validation.reason_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('staff::validation.attributes.reason'),
        ];
    }
}
