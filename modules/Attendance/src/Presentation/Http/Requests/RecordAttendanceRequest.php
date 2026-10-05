<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * طلب رصد حضور — الدقائق أعداد صحيحة غير سالبة ضمن حدود config.
 */
final class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Attendance|null $attendance */
        $attendance = Attendance::query()
            ->where('session_participant_id', (string) $this->input('session_participant_id'))
            ->first();

        return $attendance === null
            ? $this->user()->can('create', Attendance::class)
            : $this->user()->can('create', $attendance);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxAttended = (int) config('attendance.limits.max_attended_minutes', 600);
        $maxOffset = (int) config('attendance.limits.max_offset_minutes', 240);
        $reasonMin = (int) config('attendance.record.reason_min_chars', 5);
        $reasonMax = (int) config('attendance.record.reason_max_chars', 1000);

        return [
            'session_participant_id' => [
                'required',
                'string',
                'ulid',
                Rule::unique('attendances', 'session_participant_id'),
            ],
            'attended_minutes' => ['required', 'integer', 'min:0', 'max:'.$maxAttended],
            'session_minutes' => ['required', 'integer', 'min:1', 'max:'.$maxAttended],
            'joined_after_minutes' => ['sometimes', 'integer', 'min:0', 'max:'.$maxOffset],
            'left_before_minutes' => ['sometimes', 'integer', 'min:0', 'max:'.$maxOffset],
            'reason' => ['required', 'string', 'min:'.$reasonMin, 'max:'.$reasonMax],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_participant_id.required' => __('attendance::validation.participant_required'),
            'session_participant_id.ulid' => __('attendance::validation.participant_invalid'),
            'attended_minutes.required' => __('attendance::validation.attended_minutes_required'),
            'attended_minutes.integer' => __('attendance::validation.minutes_integer'),
            'attended_minutes.min' => __('attendance::validation.minutes_min'),
            'attended_minutes.max' => __('attendance::validation.minutes_max'),
            'session_minutes.required' => __('attendance::validation.session_minutes_required'),
            'session_minutes.integer' => __('attendance::validation.minutes_integer'),
            'session_minutes.min' => __('attendance::validation.session_minutes_min'),
            'joined_after_minutes.integer' => __('attendance::validation.minutes_integer'),
            'joined_after_minutes.min' => __('attendance::validation.minutes_min'),
            'left_before_minutes.integer' => __('attendance::validation.minutes_integer'),
            'left_before_minutes.min' => __('attendance::validation.minutes_min'),
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
            'session_participant_id' => __('attendance::attributes.session_participant_id'),
            'attended_minutes' => __('attendance::attributes.attended_minutes'),
            'session_minutes' => __('attendance::attributes.session_minutes'),
            'joined_after_minutes' => __('attendance::attributes.joined_after_minutes'),
            'left_before_minutes' => __('attendance::attributes.left_before_minutes'),
            'reason' => __('attendance::attributes.reason'),
        ];
    }
}
