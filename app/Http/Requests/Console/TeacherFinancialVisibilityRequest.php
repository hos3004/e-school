<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

final class TeacherFinancialVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('staff.contract.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['financials_visible' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:1000']];
    }
}
