<?php

declare(strict_types=1);

namespace App\Infrastructure\Assignments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\ValueObjects\AssignmentAudience;

final readonly class DatabaseAssignmentAudienceQueries implements AssignmentAudienceQueries
{
    public function forUser(string $organizationId, string $userId): AssignmentAudience
    {
        $studentProfileId = DB::table('student_profiles')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->value('id');
        $staffProfileId = DB::table('staff_profiles')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->value('id');

        $groupIds = [];
        $courseIds = [];

        if (is_string($studentProfileId)) {
            $groupIds = DB::table('group_memberships')
                ->join('groups', 'groups.id', '=', 'group_memberships.group_id')
                ->where('group_memberships.student_profile_id', $studentProfileId)
                ->where('group_memberships.status', 'active')
                ->whereNull('group_memberships.left_at')
                ->where('groups.organization_id', $organizationId)
                ->whereNull('groups.deleted_at')
                ->whereNotExists(function ($query) use ($organizationId, $studentProfileId): void {
                    $query->selectRaw('1')
                        ->from('group_programs')
                        ->join('enrollments', 'enrollments.program_id', '=', 'group_programs.program_id')
                        ->whereColumn('group_programs.group_id', 'group_memberships.group_id')
                        ->where('enrollments.organization_id', $organizationId)
                        ->where('enrollments.student_profile_id', $studentProfileId)
                        ->whereNull('enrollments.deleted_at')
                        ->where('enrollments.status', '!=', 'active');
                })
                ->pluck('group_memberships.group_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            $courseIds = DB::table('enrollments')
                ->join('levels', 'levels.program_id', '=', 'enrollments.program_id')
                ->join('courses', 'courses.level_id', '=', 'levels.id')
                ->where('enrollments.organization_id', $organizationId)
                ->where('enrollments.student_profile_id', $studentProfileId)
                ->where('enrollments.status', 'active')
                ->whereNull('enrollments.deleted_at')
                ->where('courses.organization_id', $organizationId)
                ->where('courses.is_active', true)
                ->whereNull('courses.deleted_at')
                ->distinct()
                ->pluck('courses.id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
        }

        return new AssignmentAudience(
            studentProfileId: is_string($studentProfileId) ? $studentProfileId : null,
            staffProfileId: is_string($staffProfileId) ? $staffProfileId : null,
            activeGroupIds: array_values(array_unique($groupIds)),
            activeCourseIds: array_values(array_unique($courseIds)),
        );
    }

    public function staffProfileBelongsToOrganization(string $organizationId, string $staffProfileId): bool
    {
        return DB::table('staff_profiles')
            ->where('id', $staffProfileId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function targetBelongsToOrganization(
        string $organizationId,
        string $courseId,
        ?string $groupId,
    ): bool {
        $course = DB::table('courses')
            ->join('levels', 'levels.id', '=', 'courses.level_id')
            ->where('courses.id', $courseId)
            ->where('courses.organization_id', $organizationId)
            ->where('courses.is_active', true)
            ->whereNull('courses.deleted_at')
            ->first(['levels.program_id']);

        if ($course === null) {
            return false;
        }

        if ($groupId === null) {
            return true;
        }

        return DB::table('groups')
            ->join('group_programs', 'group_programs.group_id', '=', 'groups.id')
            ->where('groups.id', $groupId)
            ->where('groups.organization_id', $organizationId)
            ->where('groups.status', 'active')
            ->whereNull('groups.deleted_at')
            ->where('group_programs.program_id', (string) $course->program_id)
            ->exists();
    }

    public function teacherCanTeachTarget(
        string $organizationId,
        string $staffProfileId,
        string $courseId,
        ?string $groupId,
    ): bool {
        if (!$this->staffProfileBelongsToOrganization($organizationId, $staffProfileId)
            || !$this->targetBelongsToOrganization($organizationId, $courseId, $groupId)
            || !DB::table('teacher_courses')
                ->where('staff_profile_id', $staffProfileId)
                ->where('course_id', $courseId)
                ->exists()) {
            return false;
        }

        if ($groupId === null) {
            return true;
        }

        $today = CarbonImmutable::now('UTC')->toDateString();

        return DB::table('group_teachers')
            ->where('group_id', $groupId)
            ->where('staff_profile_id', $staffProfileId)
            ->where(function ($query) use ($courseId): void {
                $query->whereNull('course_id')->orWhere('course_id', $courseId);
            })
            ->whereDate('assigned_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('assigned_to')->orWhereDate('assigned_to', '>=', $today);
            })
            ->exists();
    }

    public function studentProfileIdsForTarget(
        string $organizationId,
        string $courseId,
        ?string $groupId,
    ): array {
        if (!$this->targetBelongsToOrganization($organizationId, $courseId, $groupId)) {
            return [];
        }

        if ($groupId !== null) {
            return DB::table('group_memberships')
                ->where('group_id', $groupId)
                ->where('status', 'active')
                ->whereNull('left_at')
                ->whereExists(function ($query) use ($organizationId): void {
                    $query->selectRaw('1')
                        ->from('group_programs')
                        ->join('enrollments', 'enrollments.program_id', '=', 'group_programs.program_id')
                        ->whereColumn('group_programs.group_id', 'group_memberships.group_id')
                        ->whereColumn('enrollments.student_profile_id', 'group_memberships.student_profile_id')
                        ->where('enrollments.organization_id', $organizationId)
                        ->where('enrollments.status', 'active')
                        ->whereNull('enrollments.deleted_at');
                })
                ->distinct()
                ->pluck('student_profile_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all();
        }

        return DB::table('enrollments')
            ->join('levels', 'levels.program_id', '=', 'enrollments.program_id')
            ->join('courses', 'courses.level_id', '=', 'levels.id')
            ->where('enrollments.organization_id', $organizationId)
            ->where('enrollments.status', 'active')
            ->whereNull('enrollments.deleted_at')
            ->where('courses.id', $courseId)
            ->where('courses.organization_id', $organizationId)
            ->distinct()
            ->pluck('enrollments.student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function teacherIsAssignedToTarget(
        string $organizationId,
        string $userId,
        string $staffProfileId,
        string $courseId,
        ?string $groupId,
    ): bool {
        $ownsStaffProfile = DB::table('staff_profiles')
            ->where('id', $staffProfileId)
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$ownsStaffProfile) {
            return false;
        }

        return $this->teacherCanTeachTarget($organizationId, $staffProfileId, $courseId, $groupId);
    }
}
