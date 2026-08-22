<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Academics\Domain\Models\Program;

/**
 * طلب إنشاء برنامج أكاديمي.
 */
final class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Program::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'code' => [
                'required',
                'string',
                'max:32',
                'unique:programs,code',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => ['required', 'array', 'min:1'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'duration_weeks' => ['nullable', 'integer', 'min:1'],
            'default_session_minutes' => ['required', 'integer', 'min:15'],
            'default_rate' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('academics::errors.program_code_taken'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'organization_id',
            'code',
            'name',
            'description',
            'duration_weeks',
            'default_session_minutes',
            'default_rate',
            'currency',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('academics::attributes.'.$field)],
        )->all();
    }
}
