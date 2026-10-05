<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/** إزالة الطالب من معلمه: إيقاف جدوله وإلغاء حصصه القادمة. */
final class RemoveIndividualTeacherRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'ulid'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
