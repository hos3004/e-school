<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTeacherLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.leave.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'staff_profile_id.required' => __('staff::validation.staff_profile_required'),
            'ends_at.after' => __('staff::validation.leave_period_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'staff_profile_id' => __('staff::validation.attributes.staff_profile'),
            'starts_at' => __('staff::validation.attributes.starts_at'),
            'ends_at' => __('staff::validation.attributes.ends_at'),
        ];
    }
}
