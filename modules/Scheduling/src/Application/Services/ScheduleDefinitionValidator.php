<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\Support\BusinessRuleViolation;

final readonly class ScheduleDefinitionValidator
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
        private EnrollmentAdministrationQueries $enrollments,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function validate(string $organizationId, array $data): void
    {
        $groupId = self::nullableId($data['group_id'] ?? null);
        $studentId = self::nullableId($data['student_profile_id'] ?? null);
        if (($groupId === null) === ($studentId === null)) {
            throw BusinessRuleViolation::make('scheduling.target_invalid', 'scheduling::errors.target_invalid');
        }

        $courseId = (string) ($data['course_id'] ?? '');
        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;
        if ($course === null || $course->programId === null) {
            throw BusinessRuleViolation::make('scheduling.course_not_found', 'scheduling::errors.course_not_found');
        }

        $staffProfileId = (string) ($data['staff_profile_id'] ?? '');
        if (!$this->staff->isActiveTeacherForOrganization($organizationId, $staffProfileId)
            || !$this->qualifications->isQualified($staffProfileId, $courseId)) {
            throw BusinessRuleViolation::make('scheduling.teacher_not_eligible', 'scheduling::errors.teacher_not_eligible');
        }

        $startsOn = CarbonImmutable::parse((string) $data['starts_on'], 'UTC');
        $endsOn = isset($data['ends_on'])
            ? CarbonImmutable::parse((string) $data['ends_on'], 'UTC')
            : null;
        if ($endsOn !== null && $endsOn->lessThan($startsOn)) {
            throw BusinessRuleViolation::make('scheduling.ends_before_start', 'scheduling::errors.ends_before_start');
        }

        $duration = (int) ($data['duration_minutes'] ?? 0);
        if (!in_array($duration, (array) config('scheduling.session_durations', []), true)) {
            throw BusinessRuleViolation::make('scheduling.duration_invalid', 'scheduling::errors.duration_invalid');
        }

        try {
            new DateTimeZone((string) ($data['timezone'] ?? ''));
        } catch (\Throwable) {
            throw BusinessRuleViolation::make('scheduling.timezone_invalid', 'scheduling::errors.timezone_invalid');
        }

        if ($groupId !== null) {
            $this->validateGroup(
                $organizationId,
                $groupId,
                $course->programId,
                $courseId,
                $staffProfileId,
                $startsOn,
                $endsOn,
                $course->sessionMode,
            );

            return;
        }

        if ($course->sessionMode === 'group') {
            throw BusinessRuleViolation::make('scheduling.course_mode_mismatch', 'scheduling::errors.course_mode_mismatch');
        }

        $enrollment = $this->enrollments->schedulableEnrollmentIdsByStudent(
            $organizationId,
            $course->programId,
            [$studentId],
        );
        if (!isset($enrollment[$studentId])) {
            throw BusinessRuleViolation::make('scheduling.student_not_schedulable', 'scheduling::errors.student_not_schedulable');
        }
    }

    private function validateGroup(
        string $organizationId,
        string $groupId,
        string $programId,
        string $courseId,
        string $staffProfileId,
        CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn,
        ?string $sessionMode,
    ): void {
        if ($sessionMode === 'individual') {
            throw BusinessRuleViolation::make('scheduling.course_mode_mismatch', 'scheduling::errors.course_mode_mismatch');
        }

        $group = collect($this->groups->activeGroupsForScheduling($organizationId))
            ->first(static fn (SchedulingGroupData $item): bool => $item->id === $groupId);
        if (!$group instanceof SchedulingGroupData || !in_array($programId, $group->programIds, true)) {
            throw BusinessRuleViolation::make('scheduling.group_not_eligible', 'scheduling::errors.group_not_eligible');
        }

        $assignment = collect($group->teacherAssignments)->first(
            static fn ($item): bool => $item->staffProfileId === $staffProfileId
                && $item->courseId === $courseId
                && ($item->assignedFrom === null || $item->assignedFrom <= $startsOn->toDateString())
                && ($item->assignedTo === null || $endsOn === null || $item->assignedTo >= $endsOn->toDateString()),
        );
        if ($assignment === null) {
            throw BusinessRuleViolation::make('scheduling.teacher_not_assigned', 'scheduling::errors.teacher_not_assigned');
        }
    }

    private static function nullableId(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
