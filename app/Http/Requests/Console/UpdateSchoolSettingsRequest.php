<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Organization\Domain\Enums\Weekday;

final class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('organizations.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array:ar'],
            'name.ar' => ['required', 'string', 'max:200'],
            'default_timezone' => ['required', 'timezone:all'],
            'week_starts_on' => ['sometimes', 'required', Rule::enum(Weekday::class)],
            'version' => ['required', 'string', 'size:64'],
        ];
    }
}
