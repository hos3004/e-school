<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * طلب اعتماد حضور — لا حقول إدخال؛ الاعتماد يختم الحالة القائمة.
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
        return [];
    }
}
