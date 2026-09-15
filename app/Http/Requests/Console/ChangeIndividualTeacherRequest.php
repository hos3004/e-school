<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/** تغيير معلم جدول فردي قائم؛ ملكية الجدول للطالب تُفحص في خدمة الجدولة. */
final class ChangeIndividualTeacherRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'ulid'],
            'staff_profile_id' => ['required', 'string', 'size:26'],
            // سعر حصة المعلم الجديد في كورس الجدول — اختياري، يسري من اليوم.
            'session_rate_major' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
