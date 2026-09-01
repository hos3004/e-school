<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

final class RequestSessionPostponementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('session.postpone.request');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proposed_start' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
