<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Modules\Scheduling\Domain\Contracts\TeacherTeachingLoadQueries;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\TeachingLine;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;

/**
 * ما يدرّسه المعلم فعلًا الآن: جداوله السارية — فردية كانت أو لمجموعة — مع
 * روابط التدريس المعلقة التي لم تُجدول بعد.
 *
 * الجداول هي مصدر العلاقة بين المعلم وطلابه في هذه المدرسة، فالقراءة منها لا من
 * انتساب مجموعة. المواعيد تُقرأ من خانات الأسبوع، وعند غيابها من قاعدة التكرار.
 */
final readonly class TeacherTeachingLoadQueryService implements TeacherTeachingLoadQueries
{
    public function forTeacher(string $organizationId, string $staffProfileId): array
    {
        $today = now('UTC')->toDateString();

        $scheduled = Schedule::query()
            ->forOrganization($organizationId)
            ->forStaff($staffProfileId)
            ->active()
            ->where('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->with('weeklySlots')
            ->orderBy('created_at')
            ->get()
            ->map(static fn (Schedule $schedule): TeachingLine => new TeachingLine(
                id: (string) $schedule->getKey(),
                courseId: (string) $schedule->course_id,
                studentProfileId: $schedule->student_profile_id === null
                    ? null
                    : (string) $schedule->student_profile_id,
                groupId: $schedule->group_id === null ? null : (string) $schedule->group_id,
                sessionType: (string) $schedule->session_type,
                weeklySlots: self::slots($schedule),
                durationMinutes: (int) $schedule->duration_minutes,
                timezone: (string) $schedule->timezone,
                startsOn: $schedule->starts_on->toDateString(),
                endsOn: $schedule->ends_on?->toDateString(),
            ))
            ->all();

        $pending = PendingTeachingAssignment::query()
            ->where('organization_id', $organizationId)
            ->where('staff_profile_id', $staffProfileId)
            ->whereNotExists(function ($query) use ($organizationId, $staffProfileId, $today): void {
                $query->selectRaw('1')->from('schedules')
                    ->whereColumn('schedules.student_profile_id', 'pending_teaching_assignments.student_profile_id')
                    ->whereColumn('schedules.course_id', 'pending_teaching_assignments.course_id')
                    ->where('schedules.organization_id', $organizationId)
                    ->where('schedules.staff_profile_id', $staffProfileId)
                    ->where('schedules.is_active', true)
                    ->where(fn ($dates) => $dates->whereNull('schedules.ends_on')
                        ->orWhere('schedules.ends_on', '>=', $today));
            })
            ->orderBy('created_at')
            ->get()
            ->map(static fn (PendingTeachingAssignment $assignment): TeachingLine => new TeachingLine(
                id: (string) $assignment->getKey(),
                courseId: (string) $assignment->course_id,
                studentProfileId: (string) $assignment->student_profile_id,
                groupId: null,
                sessionType: (string) $assignment->session_type,
                durationMinutes: (int) $assignment->duration_minutes,
                awaitingSchedule: true,
            ))
            ->all();

        return [...$scheduled, ...$pending];
    }

    /** @return list<array{weekday: int, start_time: string}> */
    private static function slots(Schedule $schedule): array
    {
        $slots = $schedule->weeklySlots
            ->map(static fn ($slot): array => [
                'weekday' => (int) $slot->weekday,
                'start_time' => substr((string) $slot->start_time, 0, 5),
            ])
            ->values()
            ->all();

        if ($slots !== []) {
            return $slots;
        }

        return array_map(
            static fn (int $weekday): array => [
                'weekday' => $weekday,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
            ],
            WeeklyRecurrence::fromRRule((string) $schedule->rrule)->weekdays,
        );
    }
}
