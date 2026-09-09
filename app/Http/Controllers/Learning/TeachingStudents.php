<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** Composes public DTOs, returning only the teacher's educational projection. */
final readonly class TeachingStudents
{
    public function __construct(
        private GroupAdministrationQueries $groups,
        private IndividualTeachingAssignments $individual,
        private StudentDirectoryQueries $students,
        private UserAccountDirectory $accounts,
        private AcademicCatalogQueries $academics,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function roster(string $organizationId, string $staffId, string $locale): array
    {
        $today = now('UTC')->toDateString();
        $assignments = [];
        foreach ($this->groups->assignmentsForTeacher($organizationId, $staffId) as $assignment) {
            if ($assignment->groupStatus !== 'active'
                || ($assignment->assignedFrom !== null && substr($assignment->assignedFrom, 0, 10) > $today)
                || ($assignment->assignedTo !== null && substr($assignment->assignedTo, 0, 10) < $today)) {
                continue;
            }
            foreach ($this->groups->membershipsForGroup($organizationId, $assignment->groupId) as $member) {
                if ($member->status !== 'active' || $member->leftAt !== null
                    || ($member->joinedAt !== null && substr($member->joinedAt, 0, 10) > $today)) {
                    continue;
                }
                $assignments[$member->studentProfileId][$assignment->groupId] = [
                    'id' => $assignment->groupId,
                    'name' => $assignment->groupName[$locale] ?? $assignment->groupName['ar'] ?? $assignment->groupCode,
                    'kind' => 'group',
                ];
            }
        }
        $individual = $this->individual->activeForTeacher($organizationId, $staffId);
        $courses = $this->academics->coursesByIds($organizationId, array_values(array_unique(array_map(
            static fn ($assignment): string => $assignment->courseId, $individual,
        ))));
        foreach ($individual as $assignment) {
            $course = $courses[$assignment->courseId] ?? null;
            if ($course === null) {
                continue;
            }
            $assignments[$assignment->studentProfileId][$assignment->id] = [
                'id' => $assignment->id,
                'name' => ($course->name[$locale] ?? $course->name['ar'] ?? $course->code).($assignment->awaitingSchedule ? ' — '.__('learning.awaiting_schedule') : ''),
                'kind' => $assignment->sessionType,
            ];
        }
        $profiles = $this->students->byIds($organizationId, array_keys($assignments));
        $accounts = $this->accounts->findMany($organizationId, array_values(array_map(
            static fn ($profile): string => $profile->userId, $profiles,
        )));
        $result = [];
        foreach ($profiles as $profile) {
            $account = $accounts[$profile->userId] ?? null;
            if ($profile->archived || $account === null) {
                continue;
            }
            $result[$profile->id] = ['id' => $profile->id, 'name' => $account->name,
                'code' => $profile->studentCode, 'tracks' => array_values($assignments[$profile->id])];
        }
        uasort($result, static fn (array $first, array $second): int => strcmp($first['name'], $second['name']));

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function profile(string $organizationId, string $staffId, string $studentId, string $locale): ?array
    {
        $student = $this->roster($organizationId, $staffId, $locale)[$studentId] ?? null;
        if ($student === null) {
            return null;
        }
        $student['sessions'] = [];

        return $student;
    }
}
