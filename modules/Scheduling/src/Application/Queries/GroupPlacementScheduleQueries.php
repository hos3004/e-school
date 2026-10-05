<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;

/** Read-only console boundary. Group/course identifiers and arrays; no model leaves Scheduling. */
final readonly class GroupPlacementScheduleQueries
{
    /**
     * @param list<string> $groupIds
     * @return array<string, list<array<string, mixed>>>
     */
    public function forGroups(string $organizationId, string $courseId, array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }
        $result = [];
        $schedules = Schedule::query()->forOrganization($organizationId)
            ->where('course_id', $courseId)->whereIn('group_id', $groupIds)
            ->where('is_active', true)->with('weeklySlots')->orderBy('starts_on')->get();
        foreach ($schedules as $schedule) {
            $rule = WeeklyRecurrence::fromRRule($schedule->rrule);
            $slots = $schedule->weeklySlots->map(static fn ($slot): array => [
                'weekday' => (int) $slot->weekday, 'start_time' => substr((string) $slot->start_time, 0, 5),
            ])->values()->all();
            if ($slots === []) {
                $slots = array_map(static fn (int $weekday): array => ['weekday' => $weekday, 'start_time' => substr($schedule->start_time, 0, 5)], $rule->weekdays);
            }
            $result[(string) $schedule->group_id][] = [
                'id' => $schedule->id, 'staff_profile_id' => $schedule->staff_profile_id,
                'duration_minutes' => $schedule->duration_minutes, 'timezone' => $schedule->timezone,
                'starts_on' => $schedule->starts_on->toDateString(), 'ends_on' => $schedule->ends_on?->toDateString(),
                'interval_weeks' => $rule->intervalWeeks, 'weekly_slots' => $slots,
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function forGroup(string $organizationId, string $groupId): array
    {
        return Schedule::query()->forOrganization($organizationId)->where('group_id', $groupId)
            ->where('is_active', true)->with('weeklySlots')->orderBy('starts_on')->get()
            ->map(static function (Schedule $schedule): array {
                $rule = WeeklyRecurrence::fromRRule($schedule->rrule);
                $slots = $schedule->weeklySlots->map(static fn ($slot): array => [
                    'weekday' => (int) $slot->weekday, 'start_time' => substr((string) $slot->start_time, 0, 5),
                ])->values()->all();
                if ($slots === []) {
                    $slots = array_map(static fn (int $weekday): array => ['weekday' => $weekday, 'start_time' => substr($schedule->start_time, 0, 5)], $rule->weekdays);
                }

                return [
                    'id' => (string) $schedule->id, 'course_id' => (string) $schedule->course_id,
                    'staff_profile_id' => (string) $schedule->staff_profile_id,
                    'duration_minutes' => $schedule->duration_minutes, 'timezone' => $schedule->timezone,
                    'starts_on' => $schedule->starts_on->toDateString(), 'ends_on' => $schedule->ends_on?->toDateString(),
                    'interval_weeks' => $rule->intervalWeeks, 'weekly_slots' => $slots,
                ];
            })->all();
    }
}
