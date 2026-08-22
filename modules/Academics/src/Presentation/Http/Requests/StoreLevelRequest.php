<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Academics\Domain\Models\Level;

/**
 * طلب إنشاء مستوى داخل برنامج.
 */
final class StoreLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Level::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['required', 'string', 'size:26', 'exists:programs,id'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                'unique:levels,code,NULL,id,program_id,'.(string) $this->input('program_id'),
            ],
            'name' => ['required', 'array', 'min:1'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'program_id.exists' => __('academics::errors.program_not_found'),
            'code.unique' => __('academics::errors.level_code_taken'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'program_id',
            'code',
            'name',
            'sort_order',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('academics::attributes.'.$field)],
        )->all();
    }
}
