<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Illuminate\Http\Request;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Groups\Domain\ValueObjects\TeacherGroupAssignmentData;
use Modules\Scheduling\Domain\Contracts\TeacherTeachingLoadQueries;
use Modules\Scheduling\Domain\ValueObjects\TeachingLine;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\ValueObjects\StudentDirectoryData;

/**
 * ما يقدّمه المعلم فعلًا: برامجه ← كورساته ← طلاب كل كورس.
 *
 * المدرسة تشتغل بالكورسات الفردية، فعلاقة المعلم بطالبه جدولٌ لا انتساب مجموعة؛
 * لذلك يُبنى العرض من جداول التدريس أولًا، وتُضاف المجموعات المُسنَدة له حين
 * توجد. الكورس الفردي يعرض أسماء طلابه لا عددهم فقط.
 *
 * تركيب فقط: DTOs من الموديولات المالكة بلا join ولا Model عابر للحدود. روابط
 * صفحات الطلاب والمجموعات تُحجب على الخادم عند غياب الصلاحية.
 */
final readonly class TeacherPortfolioData
{
    public function __construct(
        private TeacherTeachingLoadQueries $teaching,
        private GroupAdministrationQueries $groups,
        private AcademicCatalogQueries $academics,
        private StudentDirectoryQueries $students,
    ) {}

    /** @return array<string, mixed> */
    public function forTeacher(Request $request, string $organizationId, string $staffProfileId): array
    {
        $user = $request->user();
        $canSeeStudents = (bool) $user?->can('student.view.any');
        $canSeeGroups = (bool) $user?->can('group.view');

        $lines = $this->teaching->forTeacher($organizationId, $staffProfileId);
        $assignments = $this->activeAssignments($organizationId, $staffProfileId);

        $courses = $this->academics->coursesByIds($organizationId, array_values(array_unique([
            ...array_map(static fn (TeachingLine $line): string => $line->courseId, $lines),
            ...array_values(array_filter(array_map(
                static fn (TeacherGroupAssignmentData $item): ?string => $item->courseId,
                $assignments,
            ))),
        ])));
        $programs = $this->academics->programsByIds($organizationId, array_values(array_unique(array_filter(
            array_map(static fn (AcademicCatalogItemData $course): ?string => $course->programId, $courses),
        ))));
        $levels = $this->academics->levelsByIds($organizationId, array_values(array_unique(array_filter(
            array_map(static fn (AcademicCatalogItemData $course): ?string => $course->levelId, $courses),
        ))));

        $members = $this->membersByGroup($organizationId, $assignments, $lines);
        $assignmentsByGroup = [];

        foreach ($assignments as $assignment) {
            $assignmentsByGroup[$assignment->groupId] ??= $assignment;
        }

        // جدول مجموعة بلا إسناد مسجَّل ما زال حِملًا قائمًا، فتُقرأ بياناتها من مالكها.
        $detached = array_values(array_diff(
            array_values(array_filter(array_map(
                static fn (TeachingLine $line): ?string => $line->groupId,
                $lines,
            ))),
            array_keys($assignmentsByGroup),
        ));
        $knownGroups = $detached === [] ? [] : $this->groups->groupsByIds($organizationId, $detached);
        $studentIds = array_values(array_unique([
            ...array_values(array_filter(array_map(
                static fn (TeachingLine $line): ?string => $line->studentProfileId,
                $lines,
            ))),
            ...array_merge(...array_values($members) ?: [[]]),
        ]));
        $names = $this->students->namesForProfiles($organizationId, $studentIds);
        $directory = $this->students->byIds($organizationId, $studentIds);

        /** @var array<string, array<string, mixed>> $byCourse */
        $byCourse = [];

        foreach ($lines as $line) {
            $course = $courses[$line->courseId] ?? null;
            $byCourse[$line->courseId] ??= $this->emptyCourse($line->courseId, $course, $levels);

            if ($line->groupId !== null) {
                $group = $byCourse[$line->courseId]['groups'][$line->groupId] ?? [];
                $byCourse[$line->courseId]['groups'][$line->groupId] = $this->group(
                    $line->groupId,
                    $assignmentsByGroup[$line->groupId] ?? null,
                    $knownGroups[$line->groupId] ?? null,
                    // جدولان لنفس المجموعة في نفس الكورس يجتمعان كمواعيد واحدة.
                    array_values(array_unique([...$group['slots'] ?? [], ...$this->slotLabels($line)])),
                    $line->durationMinutes ?? ($group['duration_minutes'] ?? null),
                    count($members[$line->groupId] ?? []),
                    $canSeeGroups,
                );

                continue;
            }

            if ($line->studentProfileId === null || !isset($names[$line->studentProfileId])) {
                continue;
            }

            $byCourse[$line->courseId]['students'][$line->studentProfileId] = $this->student(
                $line->studentProfileId,
                $names,
                $directory,
                $canSeeStudents,
                $this->slotLabels($line),
                $line->durationMinutes,
                $line->awaitingSchedule,
                null,
            );
        }

        foreach ($assignments as $assignment) {
            $courseId = $assignment->courseId;

            if ($courseId === null) {
                continue;
            }

            $byCourse[$courseId] ??= $this->emptyCourse($courseId, $courses[$courseId] ?? null, $levels);
            $group = $byCourse[$courseId]['groups'][$assignment->groupId] ?? [];
            $byCourse[$courseId]['groups'][$assignment->groupId] = $this->group(
                $assignment->groupId,
                $assignment,
                null,
                $group['slots'] ?? [],
                $group['duration_minutes'] ?? null,
                count($members[$assignment->groupId] ?? []),
                $canSeeGroups,
            );
        }

        // طلاب المجموعة طلاب الكورس نفسه، سواء جاءت المجموعة من إسناد أو من جدول.
        foreach ($byCourse as $courseId => $course) {
            foreach ($course['groups'] as $groupId => $group) {
                foreach ($members[$groupId] ?? [] as $studentId) {
                    if (!isset($names[$studentId])) {
                        continue;
                    }

                    $byCourse[$courseId]['students'][$studentId] ??= $this->student(
                        $studentId,
                        $names,
                        $directory,
                        $canSeeStudents,
                        $group['slots'],
                        $group['duration_minutes'],
                        false,
                        $group['name'],
                    );
                }
            }
        }

        return ['programs' => $this->grouped($byCourse, $programs, $canSeeStudents)];
    }

    /**
     * الإسنادات السارية اليوم على مجموعات لم تُختم — المُختمة حِمل سابق لا حالي.
     *
     * @return list<TeacherGroupAssignmentData>
     */
    private function activeAssignments(string $organizationId, string $staffProfileId): array
    {
        $today = now('UTC')->toDateString();

        return array_values(array_filter(
            $this->groups->assignmentsForTeacher($organizationId, $staffProfileId),
            static fn (TeacherGroupAssignmentData $item): bool => GroupStatus::tryFrom($item->groupStatus)
                    !== GroupStatus::Completed
                && ($item->assignedFrom === null || substr($item->assignedFrom, 0, 10) <= $today)
                && ($item->assignedTo === null || substr($item->assignedTo, 0, 10) >= $today),
        ));
    }

    /**
     * طلاب كل مجموعة يدرّسها المعلم، بانتساب فعلي قائم.
     *
     * @param list<TeacherGroupAssignmentData> $assignments
     * @param list<TeachingLine> $lines
     * @return array<string, list<string>>
     */
    private function membersByGroup(string $organizationId, array $assignments, array $lines): array
    {
        $today = now('UTC')->toDateString();
        $groupIds = array_values(array_unique([
            ...array_map(static fn (TeacherGroupAssignmentData $item): string => $item->groupId, $assignments),
            ...array_values(array_filter(array_map(
                static fn (TeachingLine $line): ?string => $line->groupId,
                $lines,
            ))),
        ]));
        $members = [];

        foreach ($groupIds as $groupId) {
            $members[$groupId] = array_values(array_map(
                static fn ($member): string => $member->studentProfileId,
                array_filter(
                    $this->groups->membershipsForGroup($organizationId, $groupId),
                    static fn ($member): bool => $member->leftAt === null
                        && (MembershipStatus::tryFrom($member->status)?->occupiesSeat() ?? false)
                        && ($member->joinedAt === null || substr($member->joinedAt, 0, 10) <= $today),
                ),
            ));
        }

        return $members;
    }

    /**
     * صف مجموعة مكتمل سواء جاء من إسناد المعلم أو من جدول مجموعة بلا إسناد.
     *
     * @param list<string> $slots
     * @return array<string, mixed>
     */
    private function group(
        string $groupId,
        ?TeacherGroupAssignmentData $assignment,
        ?SchedulingGroupData $known,
        array $slots,
        ?int $durationMinutes,
        int $studentsCount,
        bool $canSeeGroups,
    ): array {
        $name = $assignment !== null
            ? ($this->localized($assignment->groupName) ?: $assignment->groupCode)
            : ($known === null ? $groupId : ($this->localized($known->name) ?: $known->code));
        $status = $assignment !== null ? $assignment->groupStatus : $known?->status;

        return [
            'id' => $groupId,
            'name' => $name,
            'code' => $assignment !== null ? $assignment->groupCode : $known?->code,
            'status' => $status === null ? null : __('groups::status.group.'.$status),
            'role' => $assignment === null ? null : __('groups::status.teacher_role.'.$assignment->role),
            'url' => $canSeeGroups ? route('console.groups.show', ['group' => $groupId]) : null,
            'slots' => $slots,
            'duration_minutes' => $durationMinutes,
            'students_count' => $studentsCount,
        ];
    }

    /**
     * @param array<string, AcademicCatalogItemData> $levels
     * @return array<string, mixed>
     */
    private function emptyCourse(string $courseId, ?AcademicCatalogItemData $course, array $levels): array
    {
        $level = $course?->levelId === null ? null : ($levels[$course->levelId] ?? null);

        return [
            'id' => $courseId,
            'name' => $course === null ? $courseId : ($this->localized($course->name) ?: $course->code),
            'code' => $course?->code,
            'program_id' => $course?->programId,
            'session_mode' => $course?->sessionMode === null
                ? null
                : __('academics::filament.session_modes.'.$course->sessionMode),
            'level' => $level === null ? null : ($this->localized($level->name) ?: $level->code),
            'default_duration_minutes' => $course?->defaultDurationMinutes,
            'sessions_per_week' => $course?->sessionsPerWeek,
            'total_sessions' => $course?->totalSessions,
            'students' => [],
            'groups' => [],
        ];
    }

    /**
     * @param array<string, string> $names
     * @param array<string, StudentDirectoryData> $directory
     * @param list<string> $slots
     * @return array<string, mixed>
     */
    private function student(
        string $studentId,
        array $names,
        array $directory,
        bool $canSeeStudents,
        array $slots,
        ?int $durationMinutes,
        bool $awaiting,
        ?string $group,
    ): array {
        return [
            'id' => $studentId,
            'name' => $names[$studentId] ?? $studentId,
            'code' => $directory[$studentId]->studentCode ?? null,
            'url' => $canSeeStudents
                ? route('console.students.show', ['profile' => $studentId])
                : null,
            'slots' => $slots,
            'duration_minutes' => $durationMinutes,
            'awaiting' => $awaiting,
            'group' => $group,
        ];
    }

    /** @return list<string> */
    private function slotLabels(TeachingLine $line): array
    {
        return array_values(array_map(
            static fn (array $slot): string => __('console_people.teaching.weekday_'.$slot['weekday'])
                .' '.$slot['start_time'],
            $line->weeklySlots,
        ));
    }

    /**
     * @param array<string, array<string, mixed>> $byCourse
     * @param array<string, AcademicCatalogItemData> $programs
     * @return list<array<string, mixed>>
     */
    private function grouped(array $byCourse, array $programs, bool $canSeeStudents): array
    {
        $result = [];

        foreach ($byCourse as $course) {
            $programId = $course['program_id'] ?? null;
            $key = $programId ?? '';
            $result[$key] ??= [
                'id' => $programId,
                'name' => $programId !== null && isset($programs[$programId])
                    ? ($this->localized($programs[$programId]->name) ?: $programs[$programId]->code)
                    : __('console_people.portfolio.no_program'),
                'code' => $programId !== null && isset($programs[$programId]) ? $programs[$programId]->code : null,
                'courses' => [],
                'students_count' => 0,
            ];
            $course['students'] = $this->sortedByName(array_values($course['students']));
            $course['groups'] = array_values($course['groups']);
            $course['students_count'] = count($course['students']);
            unset($course['program_id']);
            $result[$key]['courses'][] = $course;
        }

        foreach ($result as $key => $program) {
            $result[$key]['courses'] = $this->sortedByName($program['courses']);
            $result[$key]['students_count'] = count(array_unique(array_merge(
                ...array_map(
                    static fn (array $course): array => array_column($course['students'], 'id'),
                    $program['courses'],
                ),
            )));
            $result[$key]['courses_count'] = count($program['courses']);

            // بلا صلاحية دليل الطلاب يبقى العدد وتُحجب الهوية — لا اسم ولا كود ولا رابط.
            if (!$canSeeStudents) {
                foreach ($result[$key]['courses'] as $index => $course) {
                    $result[$key]['courses'][$index]['students'] = [];
                }
            }
        }

        return $this->sortedByName(array_values($result));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function sortedByName(array $items): array
    {
        usort($items, static fn (array $first, array $second): int => strcmp(
            (string) $first['name'],
            (string) $second['name'],
        ));

        return $items;
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? (string) (reset($value) ?: '');
    }
}
