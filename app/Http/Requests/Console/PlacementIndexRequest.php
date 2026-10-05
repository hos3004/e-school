<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlacementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('student.view.any') && $this->user()->can('enrollment.create') && $this->user()->can('group.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course' => ['nullable', 'ulid'], 'application' => ['nullable', 'ulid'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['waiting', 'assigned', 'all'])],
            'scope' => ['nullable', Rule::in(['course', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
