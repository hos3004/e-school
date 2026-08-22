<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Staff\Domain\Enums\TeacherLeaveStatus;

final class DecideTeacherLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.leave.decide') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::enum(TeacherLeaveStatus::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => __('staff::validation.decision_required'),
            'reason.required' => __('staff::validation.reason_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => __('staff::validation.attributes.decision'),
            'reason' => __('staff::validation.attributes.reason'),
        ];
    }
}
