<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * طلب اعتماد حضور بسبب تشغيلي موثّق؛ الاعتماد يختم الحالة القائمة.
 */
final class ConfirmAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Attendance|null $attendance */
        $attendance = $this->route('attendance');

        return $attendance !== null && $this->user()->can('confirm', $attendance);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:'.(int) config('attendance.confirm.reason_min_chars', 5),
                'max:'.(int) config('attendance.confirm.reason_max_chars', 1000),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => __('attendance::validation.reason_required'),
            'reason.min' => __('attendance::validation.reason_min'),
            'reason.max' => __('attendance::validation.reason_max'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => __('attendance::attributes.reason')];
    }
}
