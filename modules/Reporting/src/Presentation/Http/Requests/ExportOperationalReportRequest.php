<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;

final class ExportOperationalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('report.export');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', Rule::in(['today', 'yesterday', 'this_week', 'previous_week', 'this_month', 'custom'])],
            'from' => ['nullable', 'required_if:preset,custom', 'date_format:Y-m-d'],
            'until' => ['nullable', 'required_if:preset,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [Rule::enum(SessionStatus::class)],
            'attendance_statuses' => ['sometimes', 'array'],
            'attendance_statuses.*' => [Rule::enum(AttendanceStatus::class)],
            'session_types' => ['sometimes', 'array'],
            'session_types.*' => [Rule::in(array_keys((array) config('academic.session_types', [])))],
            'student_profile_id' => ['nullable', 'ulid'],
            'staff_profile_id' => ['nullable', 'ulid'],
            'original_staff_profile_id' => ['nullable', 'ulid'],
            'group_id' => ['nullable', 'ulid'],
            'course_id' => ['nullable', 'ulid'],
            'report_status' => ['nullable', Rule::in(['submitted', 'late', 'missing'])],
            'search' => ['nullable', 'string', 'max:'.(int) config('reporting.operational.search_max_chars')],
        ];
    }
}
