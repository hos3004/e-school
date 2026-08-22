<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إنشاء حصة جديدة.
 */
final class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sessions.session.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'schedule_id' => ['nullable', 'string', 'size:26'],
            'group_id' => ['nullable', 'string', 'size:26'],
            'course_id' => ['required', 'string', 'size:26'],
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'substitute_for_staff_id' => ['nullable', 'string', 'size:26'],
            'session_type' => ['required', 'string', 'max:50'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'title' => ['required', 'array'],
            'title.ar' => ['required_with:title', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_end.after' => __('sessions::errors.end_before_start'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('sessions::fields.organization'),
            'course_id' => __('sessions::fields.course'),
            'staff_profile_id' => __('sessions::fields.staff_profile'),
            'session_type' => __('sessions::fields.session_type'),
            'scheduled_start' => __('sessions::fields.scheduled_start'),
            'scheduled_end' => __('sessions::fields.scheduled_end'),
            'title' => __('sessions::fields.title'),
            'notes' => __('sessions::fields.notes'),
        ];
    }
}
