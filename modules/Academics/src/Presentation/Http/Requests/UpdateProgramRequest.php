<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Academics\Domain\Models\Program;

/**
 * طلب تحديث برنامج أكاديمي.
 */
final class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('program'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $programId = (string) $this->route('program')?->getKey();

        return [
            'code' => [
                'sometimes',
                'string',
                'max:32',
                'unique:programs,code,'.$programId,
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => ['sometimes', 'array', 'min:1'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'duration_weeks' => ['nullable', 'integer', 'min:1'],
            'default_session_minutes' => ['sometimes', 'integer', 'min:15'],
            'default_rate' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
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
}
