<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إضافة طالب قائم إلى دورة أخرى. البرنامج يُستنبط من الكورس على الخادم،
 * والصلاحية على السجل تُفحص في الـcontroller.
 */
class StudentPlacementRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'ulid'],
            'group_id' => ['required', 'ulid'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
