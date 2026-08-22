<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * طلب تجاوز حالة الحضور — الحالة الجديدة من قيم الـ enum،
 * والسبب إلزامي وفق قاعدة التدقيق وبحدود config.
 */
final class OverrideAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Attendance|null $attendance */
        $attendance = $this->route('attendance');

        return $attendance !== null && $this->user()->can('override', $attendance);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::enum(AttendanceStatus::class),
            ],
            'reason' => [
                'required',
                'string',
                'min:'.(int) config('attendance.override.reason_min_chars', 5),
                'max:'.(int) config('attendance.override.reason_max_chars', 1000),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => __('attendance::validation.status_required'),
            'status.enum' => __('attendance::validation.status_invalid'),
            'reason.required' => __('attendance::validation.reason_required'),
            'reason.min' => __('attendance::validation.reason_min'),
            'reason.max' => __('attendance::validation.reason_max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => __('attendance::attributes.status'),
            'reason' => __('attendance::attributes.reason'),
        ];
    }
}
