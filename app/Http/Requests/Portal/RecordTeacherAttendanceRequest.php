<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceStatus;

final class RecordTeacherAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('attendance.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', Rule::enum(AttendanceStatus::class)],
            /*
             * السبب مطلوب عند تجاوز حالة مرصودة — لكن ما إن كان التغيير تجاوزًا
             * لا يُعرف إلا بمقارنة المرصود بالمختار، وذلك عمل الفعل لا النموذج.
             * فيُقبل هنا اختياريًا ويفرضه `OverrideAttendanceAction` عند الحاجة.
             */
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
