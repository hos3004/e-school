<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** يبني حمولة رسالة الجدول من العقود العامة فقط. */
final readonly class ScheduleNotificationPayloadFactory
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private StudentDirectoryQueries $students,
        private StaffQueries $staff,
        private SessionAdministrationQueries $sessions,
    ) {}

    /**
     * @return array{
     *   scheduleId: string, organizationId: string, staffProfileId: string,
     *   courseId: string, rrule: string, studentUserIds: list<string>,
     *   teacherUserId: string|null, courseName: array<string, string>,
     *   courseCode: string, targetName: string|array<string, string>,
     *   teacherName: string, durationMinutes: int, sessionCount: int,
     *   scheduleTimes: list<string>, timezone: string
     * }
     */
    public function forSchedule(Schedule $schedule): array
    {
        $organizationId = (string) $schedule->organization_id;
        $courseId = (string) $schedule->course_id;
        $staffProfileId = (string) $schedule->staff_profile_id;
        $studentProfileIds = $this->studentProfileIds($schedule);
        $studentDirectory = $this->students->byIds($organizationId, $studentProfileIds);
        $studentUserIds = collect($studentProfileIds)
            ->map(static fn (string $id): ?string => $studentDirectory[$id]->userId ?? null)
            ->filter(static fn (?string $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;
        $teacherName = $this->staff->namesForProfiles($organizationId, [$staffProfileId])[$staffProfileId] ?? '';
        $scheduleTimes = $this->sessions->startsForSchedule(
            $organizationId,
            (string) $schedule->getKey(),
            max(1, (int) config('scheduling.notification_summary.max_sessions', 200)),
        );

        return [
            'scheduleId' => (string) $schedule->getKey(),
            'organizationId' => $organizationId,
            'staffProfileId' => $staffProfileId,
            'courseId' => $courseId,
            'rrule' => (string) $schedule->rrule,
            'studentUserIds' => $studentUserIds,
            'teacherUserId' => $this->staff->userIdForProfile($organizationId, $staffProfileId),
            'courseName' => $course->name,
            'courseCode' => $course->code,
            'targetName' => $this->targetName($schedule, $studentProfileIds),
            'teacherName' => $teacherName,
            'durationMinutes' => (int) $schedule->duration_minutes,
            'sessionCount' => count($scheduleTimes),
            'scheduleTimes' => $scheduleTimes,
            'timezone' => (string) $schedule->timezone,
        ];
    }

    /** @return list<string> */
    private function studentProfileIds(Schedule $schedule): array
    {
        if ($schedule->student_profile_id !== null) {
            return [(string) $schedule->student_profile_id];
        }

        return collect($this->groups->membershipsForGroup(
            (string) $schedule->organization_id,
            (string) $schedule->group_id,
        ))
            ->filter(static fn ($member): bool => $member->status === 'active' && $member->leftAt === null)
            ->map(static fn ($member): string => $member->studentProfileId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param list<string> $studentProfileIds
     * @return string|array<string, string>
     */
    private function targetName(Schedule $schedule, array $studentProfileIds): string|array
    {
        if ($schedule->student_profile_id !== null) {
            return $this->students->namesForProfiles(
                (string) $schedule->organization_id,
                $studentProfileIds,
            )[(string) $schedule->student_profile_id] ?? '';
        }

        return $this->groups->groupsByIds(
            (string) $schedule->organization_id,
            [(string) $schedule->group_id],
        )[(string) $schedule->group_id]->name;
    }
}
