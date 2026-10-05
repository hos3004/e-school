<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

final class RejectPostponementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('session.postpone.approve');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
