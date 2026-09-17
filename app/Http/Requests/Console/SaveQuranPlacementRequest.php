<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class SaveQuranPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('schedule.manage') && $user->can('student.view.any');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $timezone = $this->input('timezone');
        $validTimezone = is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true);
        $today = CarbonImmutable::now($validTimezone ? $timezone : 'UTC')->toDateString();
        $rules = [
            'organization_id' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'application_id' => $this->isMethod('POST') ? ['nullable', 'ulid'] : ['prohibited'],
            'course_id' => ['prohibited'], 'group_id' => ['prohibited'], 'student_profile_id' => ['prohibited'],
            'staff_profile_id' => ['required', 'string', 'size:26'],
            // مدة مخصّصة ضمن حدود المؤسسة؛ ما خرج عن كتالوج الأسعار يلزمه سعر
            // ساري في عقد المعلم، ويتحقق منه ScheduleDefinitionValidator.
            'duration_minutes' => [
                'required', 'integer',
                'min:'.config('session_pay.min_duration'),
                'max:'.config('session_pay.max_duration'),
            ],
            // سعر حصة المعلم في هذا الكورس — اختياري؛ يُسجَّل من تاريخ البداية
            // ويُقفل سعره السابق عنده، فلا يمس حصة ماضية.
            'session_rate_major' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'rate_reason' => [$this->isMethod('GET') ? 'nullable' : 'required_with:session_rate_major', 'nullable', 'string', 'min:3', 'max:1000'],
            'interval_weeks' => ['required', 'integer', 'min:1', 'max:'.config('scheduling.individual_quran.max_interval_weeks')],
            'timezone' => ['required', 'timezone'],
            'starts_on' => ['required', 'date_format:Y-m-d', ...($this->isMethod('PATCH') || ($this->isMethod('GET') && $this->filled('schedule_id')) ? [] : ['after_or_equal:'.$today])],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];

        return $this->isMethod('GET') ? [...$rules,
            'schedule_id' => ['nullable', 'required_with:student_id', 'ulid'],
            'student_id' => ['nullable', 'required_with:schedule_id', 'ulid'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['required', 'integer', 'between:0,6', 'distinct'],
        ] : [...$rules,
            'weekly_slots' => ['required', 'array', 'min:1', 'max:7'],
            'weekly_slots.*' => ['required', 'array:weekday,start_time'],
            'weekly_slots.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'weekly_slots.*.start_time' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'staff_profile_id' => __('console_quran.teacher'),
            'duration_minutes' => __('console_quran.duration'),
            'session_rate_major' => __('console_quran.rates.session_rate'),
            'rate_reason' => __('console_quran.rates.rate_reason'),
            'interval_weeks' => __('console_quran.interval'),
            'timezone' => __('console_quran.timezone'),
            'starts_on' => __('console_quran.starts_on'),
            'ends_on' => __('console_quran.ends_on'),
            'weekly_slots' => __('console_quran.slots'),
            'weekly_slots.*.weekday' => __('console_quran.weekday'),
            'weekly_slots.*.start_time' => __('console_quran.start_time'),
            'weekdays' => __('console_quran.weekday'),
        ];
    }
}
