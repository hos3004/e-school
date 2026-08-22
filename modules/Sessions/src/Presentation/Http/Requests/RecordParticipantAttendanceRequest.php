<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب رصد حضور المشارك (دخول/خروج).
 */
final class RecordParticipantAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.participant.record_attendance');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['join', 'leave'])],
            'participant_id' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('sessions::fields.attendance_type'),
            'participant_id' => __('sessions::fields.participant'),
        ];
    }
}
