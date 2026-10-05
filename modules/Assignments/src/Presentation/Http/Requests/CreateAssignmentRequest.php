<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Assignments\Domain\Models\Assignment;

/**
 * طلب إنشاء نشاط — العنوان والتعليمات متعددتا اللغة (ar/en).
 */
final class CreateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Assignment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'],
            'course_id' => ['required', 'string', 'size:26'],
            'group_id' => ['nullable', 'string', 'size:26'],
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'title' => ['required', 'array'],
            'title.ar' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'array'],
            'instructions.ar' => ['nullable', 'string', 'max:5000'],
            'instructions.en' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['string', 'max:2048'],
            'assigned_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after:assigned_at'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'allows_late' => ['required', 'boolean'],
            'late_penalty_percent' => [
                Rule::requiredIf((bool) $this->boolean('allows_late')),
                'integer',
                'min:0',
                'max:100',
            ],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_at.after' => __('assignments::validation.due_after_assigned'),
            'max_score.min' => __('assignments::validation.max_score_min'),
            'late_penalty_percent.max' => __('assignments::validation.late_penalty_range'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'course_id' => __('assignments::attributes.course'),
            'staff_profile_id' => __('assignments::attributes.teacher'),
            'due_at' => __('assignments::attributes.due_at'),
            'max_score' => __('assignments::attributes.max_score'),
            'reason' => __('assignments::attributes.reason'),
        ];
    }
}
