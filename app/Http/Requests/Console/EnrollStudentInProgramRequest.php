<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/** قيد الطالب في برنامج إضافي؛ البرنامج يُتحقق منه في الـcontroller. */
final class EnrollStudentInProgramRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'program_id' => ['required', 'ulid'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
