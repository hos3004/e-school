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

        if ($groupId === null) {
            return DB::table('teacher_courses')
                ->where('staff_profile_id', $staffProfileId)
                ->where('course_id', $courseId)
                ->exists();
        }

        $today = CarbonImmutable::now('UTC')->toDateString();

        return DB::table('group_teachers')
            ->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->where('group_teachers.group_id', $groupId)
            ->where('group_teachers.staff_profile_id', $staffProfileId)
            ->where(function ($query) use ($courseId): void {
                $query->whereNull('group_teachers.course_id')
                    ->orWhere('group_teachers.course_id', $courseId);
            })
            ->whereDate('group_teachers.assigned_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('group_teachers.assigned_to')
                    ->orWhereDate('group_teachers.assigned_to', '>=', $today);
            })
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->exists();
    }
}
