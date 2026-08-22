<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب تسجيل مخالفة.
 *
 * الأنواع المقبولة تُقرأ من مفاتيح config('discipline.countable_events')
 * حتى لا يضيف الكود نوعًا غير معترف به في الإعدادات.
 */
final class RecordViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ViolationEvent::class);
    }

    /**
     * @return list<string>
     */
    private function allowedTypes(): array
    {
        return array_keys((array) config('discipline.countable_events', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'enrollment_id' => ['required', 'string', 'size:26'],
            'student_profile_id' => ['required', 'string', 'size:26'],
            'session_id' => ['nullable', 'string', 'size:26'],
            'type' => ['required', 'string', Rule::in($this->allowedTypes())],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => __('discipline::validation.invalid_violation_type'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('discipline::attributes.organization_id'),
            'enrollment_id' => __('discipline::attributes.enrollment_id'),
            'student_profile_id' => __('discipline::attributes.student_profile_id'),
            'session_id' => __('discipline::attributes.session_id'),
            'type' => __('discipline::attributes.type'),
            'occurred_at' => __('discipline::attributes.occurred_at'),
        ];
    }
}
