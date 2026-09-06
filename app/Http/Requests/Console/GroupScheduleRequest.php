<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GroupScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedule.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $timezone = $this->input('timezone');
        $today = now(is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone'))->toDateString();

        return [
            'organization_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'student_profile_id' => ['prohibited'], 'weekly_slots' => ['prohibited'],
            'target_type' => ['prohibited'], 'rrule' => ['prohibited'], 'is_active' => ['prohibited'],
            'group_id' => ['required', 'ulid'], 'course_id' => ['required', 'ulid'], 'staff_profile_id' => ['required', 'ulid'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'], 'weekdays.*' => ['required', 'integer', 'between:0,6', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', Rule::in(config('scheduling.session_durations'))],
            'interval_weeks' => ['required', 'integer', 'min:1', 'max:'.config('scheduling.individual_quran.max_interval_weeks')],
            'timezone' => ['required', 'timezone:all'],
            'starts_on' => ['required', 'date_format:Y-m-d', ...($this->isMethod('POST') ? ['after_or_equal:'.$today] : [])],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'schedule_id' => [$this->isMethod('GET') ? 'nullable' : 'prohibited', 'ulid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(static fn (string $key): array => [$key => __('console_sessions.fields.'.str_replace('.*', '', $key))])->all();
    }
}
