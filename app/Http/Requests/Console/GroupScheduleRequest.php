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
            'organization_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'student_profile_id' => ['prohibited'],
            'target_type' => ['prohibited'], 'rrule' => ['prohibited'], 'is_active' => ['prohibited'],
            'group_id' => ['required', 'ulid'], 'course_id' => ['required', 'ulid'], 'staff_profile_id' => ['required', 'ulid'],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'], 'weekdays.*' => ['required', 'integer', 'between:0,6', 'distinct'],
            'start_time' => ['required', 'date_format:H:i'],
            // أوقات مختلفة لكل يوم — اختياري. لو أُرسلت، أيامها يجب أن تطابق weekdays
            // بالضبط (نفس الأيام، بلا نقص أو زيادة)؛ تحقق التطابق في ScheduleDefinitionValidator.
            'weekly_slots' => ['nullable', 'array', 'max:7'],
            'weekly_slots.*.weekday' => ['required', 'integer', 'between:0,6', 'distinct'],
            'weekly_slots.*.start_time' => ['required', 'date_format:H:i'],
            // مدة مخصّصة ضمن حدود المؤسسة؛ تسعير المدة خارج كتالوج المجموعات
            // يتحقق منه ScheduleDefinitionValidator على سعر عقد المعلم بهذا الكورس.
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
            'timezone' => ['required', 'timezone:all'],
            'starts_on' => ['required', 'date_format:Y-m-d', ...($this->isMethod('POST') ? ['after_or_equal:'.$today] : [])],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'schedule_id' => [$this->isMethod('GET') ? 'nullable' : 'prohibited', 'ulid'],
            // تجاوز مهلة حماية الحصص القريبة (recurrence.edit_lock_hours) عند تعديل جدول
            // قائم فقط — استثنائي، ويحتاج سببًا صريحًا يصل للمعلم والطلاب. نفس عقد القرآن الفردي.
            'apply_immediately' => [$this->isMethod('PATCH') ? 'sometimes' : 'prohibited', 'boolean'],
            'override_reason' => [$this->isMethod('PATCH') ? Rule::requiredIf($this->boolean('apply_immediately')) : 'prohibited', 'nullable', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))->mapWithKeys(static fn (string $key): array => [$key => __('console_sessions.fields.'.str_replace('.*', '', $key))])->all();
    }
}
