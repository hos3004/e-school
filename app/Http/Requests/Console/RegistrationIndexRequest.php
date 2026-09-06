<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Students\Domain\Enums\RegistrationStatus;

final class RegistrationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('student.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course' => ['nullable', 'ulid'], 'form' => ['nullable', 'ulid'],
            'stage' => ['nullable', Rule::in(['forms', 'requests', 'accepted'])],
            'status' => ['nullable', Rule::enum(RegistrationStatus::class)],
            'search' => ['nullable', 'string', 'max:255'], 'country' => ['nullable', 'ulid'],
            'age_from' => ['nullable', 'integer', 'min:0'], 'age_to' => ['nullable', 'integer', 'min:0', 'gte:age_from'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'question' => ['nullable', 'ulid'], 'answer' => ['nullable', 'string', 'max:500'],
            'answer_from' => ['nullable', 'numeric'], 'answer_to' => ['nullable', 'numeric', 'gte:answer_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
