<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Reporting\Domain\Contracts\ClosureSnapshotQueries;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Shared\Archive\ClosureSnapshot;

/**
 * يجمع حصيلة الإقفال من عقود الموديولات — بلا استعلام مباشر على جداول غيره.
 *
 * كل نوع وحصيلته: البرنامج يُقاس ببنيته وقيوده، والكورس بحصصه، والمجموعة
 * بأعضائها. توحيد الشكل هنا كان سيضيع المعنى الذي يريده المستخدم من الأرشيف.
 */
final readonly class ClosureSnapshotQueryService implements ClosureSnapshotQueries
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private EnrollmentAdministrationQueries $enrollments,
        private GroupAdministrationQueries $groups,
        private SessionAdministrationQueries $sessions,
    ) {}

    public function forProgram(string $organizationId, string $programId): ClosureSnapshot
    {
        $structure = $this->academics->closureFactsForProgram($organizationId, $programId);
        $enrollment = $this->enrollments->closureFactsForProgram($organizationId, $programId);
        $courseIds = $this->academics->courseIdsForProgram($organizationId, $programId);
        $sessions = $this->sessions->closureFactsForCourses($organizationId, $courseIds);

        return new ClosureSnapshot(
            summary: [
                'kind' => 'program',
                'captured_at' => self::now(),
                'levels_total' => $structure['levels_total'],
                'courses_total' => $structure['courses_total'],
                'courses_open' => $structure['courses_open'],
                'courses_closed' => $structure['courses_closed'],
                'enrollments_total' => $enrollment['enrollments_total'],
                'enrollments_completed' => $enrollment['enrollments_completed'],
                'enrollments_withdrawn' => $enrollment['enrollments_withdrawn'],
                'students_distinct' => $enrollment['students_distinct'],
                'sessions_total' => $sessions['sessions_total'],
                'sessions_completed' => $sessions['sessions_completed'],
                'sessions_cancelled' => $sessions['sessions_cancelled'],
                'sessions_stale' => $sessions['sessions_stale'],
                'sessions_other' => $sessions['sessions_other'],
                'teachers_distinct' => $sessions['teachers_distinct'],
                'first_session_at' => $sessions['first_session_at'],
                'last_session_at' => $sessions['last_session_at'],
            ],
            blockers: self::blockers([
                'courses_active' => $structure['courses_active'],
                'enrollments_live' => $enrollment['enrollments_live'],
                'sessions_open' => $sessions['sessions_open'],
            ]),
        );
    }

    public function forCourse(string $organizationId, string $courseId): ClosureSnapshot
    {
        $course = $this->academics->closureFactsForCourse($organizationId, $courseId);
        $sessions = $this->sessions->closureFactsForCourses($organizationId, [$courseId]);

        return new ClosureSnapshot(
            summary: [
                'kind' => 'course',
                'captured_at' => self::now(),
                'level_id' => $course['level_id'],
                'program_id' => $course['program_id'],
                'planned_sessions' => $course['planned_sessions'],
                'sessions_total' => $sessions['sessions_total'],
                'sessions_completed' => $sessions['sessions_completed'],
                'sessions_cancelled' => $sessions['sessions_cancelled'],
                'sessions_stale' => $sessions['sessions_stale'],
                'sessions_other' => $sessions['sessions_other'],
                'students_distinct' => $sessions['students_distinct'],
                'teachers_distinct' => $sessions['teachers_distinct'],
                'first_session_at' => $sessions['first_session_at'],
                'last_session_at' => $sessions['last_session_at'],
            ],
            blockers: self::blockers([
                'sessions_open' => $sessions['sessions_open'],
            ]),
        );
    }

    public function forGroup(string $organizationId, string $groupId): ClosureSnapshot
    {
        $group = $this->groups->closureFactsForGroup($organizationId, $groupId);
        $sessions = $this->sessions->closureFactsForGroup($organizationId, $groupId);

        return new ClosureSnapshot(
            summary: [
                'kind' => 'group',
                'captured_at' => self::now(),
                'status' => $group['status'],
                'capacity' => $group['capacity'],
                'starts_on' => $group['starts_on'],
                'ends_on' => $group['ends_on'],
                'members_total' => $group['members_total'],
                'teachers_total' => $group['teachers_total'],
                'programs_total' => $group['programs_total'],
                'sessions_total' => $sessions['sessions_total'],
                'sessions_completed' => $sessions['sessions_completed'],
                'sessions_cancelled' => $sessions['sessions_cancelled'],
                'sessions_stale' => $sessions['sessions_stale'],
                'sessions_other' => $sessions['sessions_other'],
                'students_distinct' => $sessions['students_distinct'],
                'teachers_distinct' => $sessions['teachers_distinct'],
                'first_session_at' => $sessions['first_session_at'],
                'last_session_at' => $sessions['last_session_at'],
            ],
            blockers: self::blockers([
                'members_active' => $group['members_active'],
                'sessions_open' => $sessions['sessions_open'],
            ]),
        );
    }

    /**
     * يحتفظ بالموانع ذات العدد الموجب فقط — الصفر ليس مانعًا.
     *
     * @param array<string, int> $candidates
     * @return array<string, int>
     */
    private static function blockers(array $candidates): array
    {
        return array_filter($candidates, static fn (int $count): bool => $count > 0);
    }

    private static function now(): string
    {
        return CarbonImmutable::now('UTC')->toIso8601String();
    }
}
