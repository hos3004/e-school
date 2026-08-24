<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;

final readonly class DatabaseClassAudienceQueries implements ClassAudienceQueries
{
    public function usersBelongToOrganization(string $organizationId, array $userIds): bool
    {
        $ids = array_values(array_unique(array_filter(
            $userIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            return false;
        }

        return DB::table('users')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->distinct()
            ->count('id') === count($ids);
    }

    public function isGuardian(string $organizationId, string $userId): bool
    {
        return DB::table('guardian_profiles')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function isStudentTeacherConversation(string $organizationId, array $participantUserIds): bool
    {
        $ids = array_values(array_unique($participantUserIds));

        if (count($ids) !== 2) {
            return false;
        }

        $hasStudent = DB::table('student_profiles')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->whereIn('user_id', $ids)
            ->exists();
        $hasTeacher = DB::table('staff_profiles')
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->whereIn('user_id', $ids)
            ->exists();

        return $hasStudent && $hasTeacher;
    }

    public function canAccessClass(string $organizationId, string $groupId, string $userId): bool
    {
        $groupExists = DB::table('groups')
            ->where('id', $groupId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$groupExists) {
            return false;
        }

        if ($this->isCurrentlyAssignedTeacher($organizationId, $groupId, $userId)) {
            return true;
        }

        return $this->isActiveStudentMember($organizationId, $groupId, $userId);
    }

    private function isCurrentlyAssignedTeacher(string $organizationId, string $groupId, string $userId): bool
    {
        $today = CarbonImmutable::now('UTC')->toDateString();

        return DB::table('group_teachers')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'group_teachers.staff_profile_id')
            ->where('group_teachers.group_id', $groupId)
            ->where('staff_profiles.organization_id', $organizationId)
            ->where('staff_profiles.user_id', $userId)
            ->whereNull('staff_profiles.deleted_at')
            ->whereDate('group_teachers.assigned_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('group_teachers.assigned_to')
                    ->orWhereDate('group_teachers.assigned_to', '>=', $today);
            })
            ->exists();
    }

    private function isActiveStudentMember(string $organizationId, string $groupId, string $userId): bool
    {
        $student = DB::table('group_memberships')
            ->join('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
            ->where('group_memberships.group_id', $groupId)
            ->where('group_memberships.status', 'active')
            ->whereNull('group_memberships.left_at')
            ->where('student_profiles.organization_id', $organizationId)
            ->where('student_profiles.user_id', $userId)
            ->whereNull('student_profiles.deleted_at')
            ->select('student_profiles.id')
            ->first();

        if ($student === null) {
            return false;
        }

        // A frozen/withdrawn enrollment for a program served by this group
        // revokes future class access without deleting historic wall records.
        return !DB::table('group_programs')
            ->join('enrollments', 'enrollments.program_id', '=', 'group_programs.program_id')
            ->where('group_programs.group_id', $groupId)
            ->where('enrollments.organization_id', $organizationId)
            ->where('enrollments.student_profile_id', (string) $student->id)
            ->whereNull('enrollments.deleted_at')
            ->whereIn('enrollments.status', ['frozen', 'withdrawn', 'cancelled'])
            ->exists();
    }
}
