<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceStatus;

final class FollowupAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('attendance.override');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AttendanceStatus::class), Rule::in(array_map(static fn (AttendanceStatus $status): string => $status->value, array_filter(AttendanceStatus::cases(), static fn (AttendanceStatus $status): bool => !$status->isViolation())))],
            'expected_status' => ['required', Rule::enum(AttendanceStatus::class)],
            'context' => ['required', Rule::in(['accepted_excuse', 'recording_correction', 'technical_issue', 'not_held'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'status' => __('validation.attributes.status'),
            'expected_status' => __('validation.attributes.expected_status'),
            'context' => __('validation.attributes.context'),
            'note' => __('validation.attributes.note'),
        ];
    }
}
