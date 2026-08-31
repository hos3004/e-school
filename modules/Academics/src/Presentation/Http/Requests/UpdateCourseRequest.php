<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;

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
            'level_id' => ['sometimes', 'string', 'size:26', Rule::exists('levels', 'id')],
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
            'session_mode' => ['sometimes', Rule::enum(SessionMode::class)],
            'target_gender' => ['nullable', Rule::enum(TargetGender::class)],
            'age_from' => ['nullable', 'integer', 'min:'.(int) config('academics.age.minimum'), 'max:'.(int) config('academics.age.maximum')],
            'age_to' => ['nullable', 'integer', 'min:'.(int) config('academics.age.minimum'), 'max:'.(int) config('academics.age.maximum'), 'gte:age_from'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:'.(int) config('academics.session_minutes.course_minimum'), 'max:'.(int) config('academics.session_minutes.maximum')],
            'sessions_per_week' => ['nullable', 'integer', 'min:'.(int) config('academics.sessions_per_week.minimum'), 'max:'.(int) config('academics.sessions_per_week.maximum')],
            'prerequisites' => ['nullable', 'array'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['string', 'size:26', 'distinct'],
            'reason' => ['required', 'string', 'min:'.(int) config('academics.reason.minimum_length'), 'max:'.(int) config('academics.reason.maximum_length')],
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
