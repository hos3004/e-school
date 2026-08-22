<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Academics\Domain\Models\Course;

/**
 * طلب إنشاء كورس داخل مستوى.
 */
final class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Course::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'level_id' => ['required', 'string', 'size:26', 'exists:levels,id'],
            'code' => [
                'required',
                'string',
                'max:32',
                'unique:courses,code',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => ['required', 'array', 'min:1'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'total_sessions' => ['nullable', 'integer', 'min:1'],
            'completion_rules' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('academics::errors.course_code_taken'),
            'level_id.exists' => __('academics::errors.level_not_found'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'organization_id',
            'level_id',
            'code',
            'name',
            'description',
            'total_sessions',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('academics::attributes.'.$field)],
        )->all();
    }
}
