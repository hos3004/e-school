<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FollowupActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('enrollment.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['pause', 'resume', 'freeze', 'request', 'assess', 'approve', 'reject'])],
            'expected_status' => ['required', 'string', 'max:50'],
            'context' => ['required', Rule::in(['student_request', 'health_travel', 'attendance_review', 'requirements_completed', 'other'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'return_date' => ['required_if:action,pause', 'nullable', 'date_format:Y-m-d'],
            'assessment_id' => ['nullable', 'ulid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'action' => __('validation.attributes.action'),
            'expected_status' => __('validation.attributes.expected_status'),
            'context' => __('validation.attributes.context'),
            'note' => __('validation.attributes.note'),
            'return_date' => __('validation.attributes.return_date'),
            'assessment_id' => __('validation.attributes.assessment_id'),
        ];
    }
}
