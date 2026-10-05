<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/** تفعيل أو إيقاف البدء المرن لجدول فردي قائم — بلا مساس بالمعلم أو الموعد. */
final class SetScheduleFlexibleStartRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'ulid'],
            'flexible_start' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
