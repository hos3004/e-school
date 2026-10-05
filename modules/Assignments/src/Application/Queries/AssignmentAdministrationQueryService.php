<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class AssignmentAdministrationQueryService
{
    public function __construct(
        private AcademicCatalogQueries $catalog,
        private GroupAdministrationQueries $groups,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
        private StudentDirectoryQueries $students,
        private UserQueryService $users,
        private AuditQueryService $audit,
    ) {}

    /** @return array<string, string> */
    public function programOptions(string $organizationId): array
    {
        $options = [];

        foreach ($this->catalog->programs($organizationId) as $program) {
            $options[$program->id] = $this->label($program->name, $program->code);
        }

        return $options;
    }

    /** @return array<string, string> */
    public function courseOptions(string $organizationId, ?string $programId = null): array
    {
        $options = [];
        $programIds = $programId === null || $programId === ''
            ? array_keys($this->programOptions($organizationId))
            : [$programId];

        foreach ($programIds as $id) {
            foreach ($this->catalog->courses($organizationId, $id) as $course) {
                $options[$course->id] = $this->label($course->name, $course->code);
            }
        }

        asort($options);

        return $options;
    }

    public function programIdForCourse(string $organizationId, ?string $courseId): ?string
    {
        if ($courseId === null || $courseId === '') {
            return null;
        }

        return $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId]->programId ?? null;
    }

    /** @return array<string, string> */
    public function groupOptions(string $organizationId, ?string $courseId): array
    {
        $programId = $this->programIdForCourse($organizationId, $courseId);

        if ($programId === null) {
            return [];
        }

        $options = [];

        foreach ($this->groups->activeGroupsForScheduling($organizationId) as $group) {
            if (!in_array($programId, $group->programIds, true)) {
                continue;
            }

            $options[$group->id] = $this->label($group->name, $group->code);
        }

        asort($options);

        return $options;
    }

    /** @return array<string, string> */
    public function teacherOptions(
        string $organizationId,
        ?string $courseId,
        ?string $groupId,
    ): array {
        if ($courseId === null || $courseId === '') {
            return [];
        }

        $qualified = $this->qualifications->qualifiedTeacherIdsForCourse($courseId);

        if ($groupId !== null && $groupId !== '') {
            $group = collect($this->groups->activeGroupsForScheduling($organizationId))
                ->first(static fn ($candidate): bool => $candidate->id === $groupId);

            if ($group === null) {
                return [];
            }

            $assigned = [];

            foreach ($group->teacherAssignments as $assignment) {
                if ($assignment->courseId === null || $assignment->courseId === $courseId) {
                    $assigned[] = $assignment->staffProfileId;
                }
            }

            $qualified = array_values(array_intersect($qualified, $assigned));
        }

        $options = [];

        foreach ($this->staff->activeTeacherSummariesForOrganization($organizationId) as $teacher) {
            if (in_array($teacher['staff_profile_id'], $qualified, true)) {
                $options[$teacher['staff_profile_id']] = $teacher['name'];
            }
        }

        return $options;
    }

    public function courseLabel(string $organizationId, string $courseId): string
    {
        $course = $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        return $course === null ? __('assignments::messages.not_available') : $this->label($course->name, $course->code);
    }

    public function groupLabel(string $organizationId, ?string $groupId): string
    {
        if ($groupId === null || $groupId === '') {
            return __('assignments::messages.all_course_students');
        }

        $group = $this->groups->groupsByIds($organizationId, [$groupId])[$groupId] ?? null;

        return $group === null ? __('assignments::messages.not_available') : $this->label($group->name, $group->code);
    }

    public function teacherLabel(string $organizationId, string $staffProfileId): string
    {
        return $this->staff->namesForProfiles($organizationId, [$staffProfileId])[$staffProfileId]
            ?? __('assignments::messages.not_available');
    }

    /** @return array{recipients: int, pending: int, submitted: int, late: int, graded: int} */
    public function metrics(Assignment $assignment): array
    {
        $counts = $assignment->submissions()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'recipients' => (int) $counts->sum(),
            'pending' => (int) ($counts[AssignmentSubmissionStatus::Pending->value] ?? 0),
            'submitted' => (int) ($counts[AssignmentSubmissionStatus::Submitted->value] ?? 0),
            'late' => (int) ($counts[AssignmentSubmissionStatus::Late->value] ?? 0),
            'graded' => (int) ($counts[AssignmentSubmissionStatus::Graded->value] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function submissions(string $organizationId, string $assignmentId): array
    {
        $assignmentExists = Assignment::query()
            ->forOrganization($organizationId)
            ->whereKey($assignmentId)
            ->exists();

        if (!$assignmentExists) {
            return [];
        }

        $submissions = AssignmentSubmission::query()
            ->where('assignment_id', $assignmentId)
            ->orderBy('status')
            ->orderBy('student_profile_id')
            ->get();
        $studentIds = $submissions
            ->pluck('student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
        $students = $this->students->byIds($organizationId, $studentIds);
        $users = $this->users->summariesByIds(array_values(array_map(
            static fn ($student): string => $student->userId,
            $students,
        )));

        return $submissions->map(function (AssignmentSubmission $submission) use ($students, $users): array {
            $student = $students[(string) $submission->student_profile_id] ?? null;
            $user = $student === null ? null : ($users[$student->userId] ?? null);

            return [
                'id' => (string) $submission->getKey(),
                'student' => $user !== null
                    ? $user->name
                    : ($student === null ? __('assignments::messages.not_available') : $student->studentCode),
                'student_code' => $student === null ? null : $student->studentCode,
                'status' => $submission->status->label(),
                'status_value' => $submission->status->value,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'is_late' => (bool) $submission->is_late,
                'content' => $submission->content,
                'raw_score' => $submission->raw_score,
                'penalty_points' => (int) $submission->penalty_points,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function auditTrail(string $organizationId, string $assignmentId): array
    {
        $entries = $this->audit->paginateForOrganization($organizationId, [
            'auditable_type' => 'assignment',
            'auditable_id' => $assignmentId,
        ], 50);
        $actors = $this->users->summariesByIds(array_values(array_filter(array_map(
            static fn ($entry): ?string => $entry->actorId,
            $entries->items(),
        ))));

        return array_values(array_map(static function ($entry) use ($actors): array {
            $translation = 'assignments::audit_actions.'.$entry->action;

            return [
                'action' => __($translation) === $translation ? $entry->action : __($translation),
                'actor' => $entry->actorId === null
                    ? __('assignments::messages.system_actor')
                    : ($actors[$entry->actorId]->name ?? $entry->actorId),
                'reason' => $entry->reason,
                'created_at' => $entry->createdAt,
            ];
        }, $entries->items()));
    }

    /** @param array<string, string> $values */
    private function label(array $values, string $fallback): string
    {
        return (string) ($values[app()->getLocale()] ?? $values['ar'] ?? $values['en'] ?? $fallback);
    }
}
