<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Scheduling\Domain\Contracts\ScheduleDirectoryQueries;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\ScheduleTargetData;

/**
 * قراءة الجداول المعتمدة وتحويلها إلى DTOs مع أسماء الكورسات.
 *
 * اسم الكورس يأتي من عقد Academics العام لا من join عابر للحدود.
 */
final readonly class ScheduleDirectoryQueryService implements ScheduleDirectoryQueries
{
    public function __construct(
        private AcademicCatalogQueries $catalog,
    ) {}

    public function find(string $organizationId, string $scheduleId): ?ScheduleTargetData
    {
        $schedule = Schedule::query()
            ->where('organization_id', $organizationId)
            ->whereKey($scheduleId)
            ->first();

        if (!$schedule instanceof Schedule) {
            return null;
        }

        return $this->map(
            $schedule,
            $this->courseNames($organizationId, [(string) $schedule->course_id]),
        );
    }

    /** @return list<ScheduleTargetData> */
    public function activeForCourse(string $organizationId, string $courseId): array
    {
        return $this->mapMany(
            $organizationId,
            Schedule::query()
                ->where('organization_id', $organizationId)
                ->where('course_id', $courseId)
                ->where('is_active', true)
                ->get(),
        );
    }

    /** @return list<ScheduleTargetData> */
    public function activeForGroup(string $organizationId, string $groupId): array
    {
        return $this->mapMany(
            $organizationId,
            Schedule::query()
                ->where('organization_id', $organizationId)
                ->where('group_id', $groupId)
                ->where('is_active', true)
                ->get(),
        );
    }

    /** @return list<string> */
    public function activeCourseIds(string $organizationId): array
    {
        return Schedule::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->distinct()
            ->pluck('course_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * الجداول الفعّالة للعرض في قوائم الاختيار.
     *
     * الحد سقف مسح لا حد عرض: التسمية تُركَّب من اسم الكورس واسم الطالب أو
     * المجموعة، وهي بيانات موديولات أخرى لا تُضم بـjoin، فالترشيح بالنص يقع
     * بعد التحويل. السقف في config كي لا يبقى رقم سياسة في الكود.
     *
     * @return list<ScheduleTargetData>
     */
    public function active(string $organizationId, ?int $limit = null): array
    {
        $limit ??= (int) config('scheduling.messaging.schedule_scan_limit', 500);

        return $this->mapMany(
            $organizationId,
            Schedule::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->orderBy('starts_on')
                ->limit(max(1, $limit))
                ->get(),
        );
    }

    /**
     * @param Collection<int, Schedule> $schedules
     * @return list<ScheduleTargetData>
     */
    private function mapMany(string $organizationId, Collection $schedules): array
    {
        $names = $this->courseNames(
            $organizationId,
            $schedules->pluck('course_id')->map(static fn (mixed $id): string => (string) $id)->unique()->values()->all(),
        );

        return $schedules
            ->map(fn (Schedule $schedule): ScheduleTargetData => $this->map($schedule, $names))
            ->values()
            ->all();
    }

    /**
     * @param list<string> $courseIds
     * @return array<string, array<string, string>>
     */
    private function courseNames(string $organizationId, array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        $names = [];

        foreach ($this->catalog->coursesByIds($organizationId, $courseIds) as $courseId => $course) {
            $names[(string) $courseId] = $course->name;
        }

        return $names;
    }

    /** @param array<string, array<string, string>> $courseNames */
    private function map(Schedule $schedule, array $courseNames): ScheduleTargetData
    {
        $courseId = (string) $schedule->course_id;

        return new ScheduleTargetData(
            id: (string) $schedule->getKey(),
            organizationId: (string) $schedule->organization_id,
            courseId: $courseId,
            courseName: $courseNames[$courseId] ?? [],
            groupId: $schedule->group_id !== null ? (string) $schedule->group_id : null,
            studentProfileId: $schedule->student_profile_id !== null
                ? (string) $schedule->student_profile_id
                : null,
            staffProfileId: (string) $schedule->staff_profile_id,
            sessionType: (string) $schedule->session_type,
            isActive: (bool) $schedule->is_active,
        );
    }
}
