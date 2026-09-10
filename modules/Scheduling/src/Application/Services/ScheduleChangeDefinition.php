<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Shared\Support\BusinessRuleViolation;

/**
 * يحوّل الموعد الأسبوعي المقترح إلى تعريف جدول كامل يفهمه UpdateScheduleAction،
 * ويقرأ الموعد الحالي من القالب دون تكرار منطق التطبيع.
 */
final readonly class ScheduleChangeDefinition
{
    /**
     * الموعد الأسبوعي الحالي للقالب: خانات فردية إن وُجدت، وإلا أيام التكرار
     * مع ساعة البداية الواحدة.
     *
     * @return list<array{weekday: int, start_time: string}>
     */
    public function currentSlots(Schedule $schedule): array
    {
        $slots = $schedule->weeklySlots()->get(['weekday', 'start_time'])
            ->map(static fn ($slot): array => [
                'weekday' => (int) $slot->weekday,
                'start_time' => substr((string) $slot->start_time, 0, 5),
            ])
            ->all();

        if ($slots !== []) {
            usort($slots, static fn (array $left, array $right): int => $left['weekday'] <=> $right['weekday']);

            return $slots;
        }

        $startTime = substr((string) $schedule->start_time, 0, 5);

        return array_map(
            static fn (int $weekday): array => ['weekday' => $weekday, 'start_time' => $startTime],
            WeeklyRecurrence::fromRRule((string) $schedule->rrule)->weekdays,
        );
    }

    public function intervalWeeks(Schedule $schedule): int
    {
        return WeeklyRecurrence::fromRRule((string) $schedule->rrule)->intervalWeeks;
    }

    /**
     * يطبّع الخانات المقترحة ويرفض ما لا يصلح موعدًا أسبوعيًا.
     *
     * @return list<array{weekday: int, start_time: string}>
     */
    public function normalizeSlots(mixed $slots, bool $individual): array
    {
        if (!is_array($slots) || $slots === []) {
            throw BusinessRuleViolation::make('scheduling.weekly_slots_invalid', 'scheduling::errors.weekly_slots_invalid');
        }

        $normalized = [];
        $weekdays = [];

        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot['weekday'], $slot['start_time'])) {
                throw BusinessRuleViolation::make('scheduling.weekly_slots_invalid', 'scheduling::errors.weekly_slots_invalid');
            }

            $weekday = (int) $slot['weekday'];
            $startTime = is_string($slot['start_time']) ? substr($slot['start_time'], 0, 5) : '';

            if ($weekday < 0 || $weekday > 6
                || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime) !== 1
                || in_array($weekday, $weekdays, true)) {
                throw BusinessRuleViolation::make('scheduling.weekly_slots_invalid', 'scheduling::errors.weekly_slots_invalid');
            }

            $weekdays[] = $weekday;
            $normalized[] = ['weekday' => $weekday, 'start_time' => $startTime];
        }

        usort($normalized, static fn (array $left, array $right): int => $left['weekday'] <=> $right['weekday']);

        if (!$individual && count(array_unique(array_column($normalized, 'start_time'))) > 1) {
            throw BusinessRuleViolation::make(
                'scheduling.group_slots_single_time',
                'scheduling::errors.group_slots_single_time',
            );
        }

        return $normalized;
    }

    /**
     * تعريف الجدول بعد تطبيق الموعد المقترح — نفس شكل بيانات شاشة الإدارة.
     *
     * @param list<array{weekday: int, start_time: string}> $slots
     * @return array<string, mixed>
     */
    public function toScheduleData(Schedule $schedule, array $slots, int $intervalWeeks): array
    {
        $individual = $schedule->student_profile_id !== null;
        $data = [
            'target_type' => $individual ? 'student' : 'group',
            'group_id' => $individual ? null : ($schedule->group_id === null ? null : (string) $schedule->group_id),
            'student_profile_id' => $individual ? (string) $schedule->student_profile_id : null,
            'course_id' => (string) $schedule->course_id,
            'staff_profile_id' => (string) $schedule->staff_profile_id,
            'duration_minutes' => (int) $schedule->duration_minutes,
            'timezone' => (string) $schedule->timezone,
            'starts_on' => $schedule->starts_on->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
            'interval_weeks' => $intervalWeeks,
            'weekdays' => array_column($slots, 'weekday'),
            'start_time' => $slots[0]['start_time'],
        ];

        if ($individual) {
            $data['weekly_slots'] = $slots;
        }

        return $data;
    }

    /**
     * @return list<array{weekday: int, start_time: string}>
     */
    public function proposedSlots(ScheduleChangeRequest $request): array
    {
        $slots = $request->proposed_weekly_slots;

        if (is_array($slots) && $slots !== []) {
            return array_values(array_map(
                static fn (array $slot): array => [
                    'weekday' => (int) $slot['weekday'],
                    'start_time' => substr((string) $slot['start_time'], 0, 5),
                ],
                $slots,
            ));
        }

        $startTime = substr((string) $request->proposed_start_time, 0, 5);

        return array_map(
            static fn (mixed $weekday): array => ['weekday' => (int) $weekday, 'start_time' => $startTime],
            array_values((array) $request->proposed_weekdays),
        );
    }
}
