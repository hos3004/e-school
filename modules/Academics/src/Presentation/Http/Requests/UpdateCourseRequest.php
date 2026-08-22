<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تحديث كورس.
 */
final class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseId = (string) $this->route('course')?->getKey();

        return [
            'level_id' => ['sometimes', 'string', 'size:26', 'exists:levels,id'],
            'code' => [
                'sometimes',
                'string',
                'max:32',
                'unique:courses,code,'.$courseId,
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => ['sometimes', 'array', 'min:1'],
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
}
