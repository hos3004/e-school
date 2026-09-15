<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;

/** إسناد معلم لكورس فردي: مواعيد أسبوعية ومدة ضمن حدود المؤسسة. */
final class AssignIndividualTeacherRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'ulid'],
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'weekly_slots' => ['required', 'array', 'min:1', 'max:7'],
            'weekly_slots.*.weekday' => ['required', 'integer', 'between:0,6'],
            'weekly_slots.*.start_time' => ['required', 'date_format:H:i'],
            // مدة مخصّصة ضمن حدود المؤسسة؛ تسعير المدة خارج الكتالوج يتحقق منه
            // ScheduleDefinitionValidator على سعر عقد المعلم.
            'duration_minutes' => [
                'required', 'integer',
                'min:'.config('session_pay.min_duration'),
                'max:'.config('session_pay.max_duration'),
            ],
            'interval_weeks' => ['nullable', 'integer', 'min:1', 'max:8'],
            'timezone' => ['required', 'timezone'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return list<array{weekday: int, start_time: string}> */
    public function weeklySlots(): array
    {
        /** @var list<array{weekday: mixed, start_time: mixed}> $slots */
        $slots = (array) $this->validated('weekly_slots');

        return array_values(array_map(static fn (array $slot): array => [
            'weekday' => (int) $slot['weekday'],
            'start_time' => (string) $slot['start_time'],
        ], $slots));
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
