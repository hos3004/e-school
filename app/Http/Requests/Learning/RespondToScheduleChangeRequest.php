<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

/** رد الطالب على طلب تغيير الموعد الدائم: قبول أو رفض مع ملاحظة اختيارية. */
final class RespondToScheduleChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('schedule.change.respond');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:accept,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
