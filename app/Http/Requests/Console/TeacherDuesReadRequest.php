<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TeacherDuesReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('payroll.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['period' => ['nullable', 'ulid'], 'teacher' => ['nullable', 'ulid'],
            'staff_profile_id' => ['nullable', 'ulid'], 'search' => ['nullable', 'string', 'max:191'],
            'track' => ['nullable', Rule::in(['all', 'individual', 'group'])],
            'page' => ['nullable', 'integer', 'min:1']];
    }
}
