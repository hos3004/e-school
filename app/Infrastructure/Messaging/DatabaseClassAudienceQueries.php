<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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

    /**
     * أول تطبيق فعلي لتقييد من يستطيع الطالب/ولي الأمر مراسلته — قبل هذا
     * كان أي حامل message.send غير المعلم بلا تقييد إطلاقًا (انظر التعليق
     * القديم أسفل فرع المعلم). المعلم مقيّد بطلابه فقط (بدون تغيير)، الطالب
     * مقيّد بمعلميه وزملائه في نفس المجموعة وأولياء أمور هؤلاء الزملاء، وولي
     * الأمر مقيّد بمعلمي أبنائه فقط (لا يصل لأولياء أمور آخرين — خصوصية بلا
     * طلب صريح لكسرها). أي دور آخر (مثل حامل message.moderate) يبقى بلا
     * تقييد كما كان دائمًا؛ القرار هنا للمستدعي.
     */
    public function reachableRecipientUserIds(string $organizationId, string $actorUserId): ?array
    {
        $staffProfileId = DB::table('staff_profiles')
            ->where('user_id', $actorUserId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->value('id');

        if ($staffProfileId !== null) {
            return $this->reachableForTeacher($organizationId, (string) $staffProfileId);
        }

        $studentProfileId = DB::table('student_profiles')
            ->where('user_id', $actorUserId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->value('id');

        if ($studentProfileId !== null) {
            return $this->reachableForStudent($organizationId, (string) $studentProfileId);
        }

        $guardianProfileId = DB::table('guardian_profiles')
            ->where('user_id', $actorUserId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->value('id');

        if ($guardianProfileId !== null) {
            return $this->reachableForGuardian($organizationId, (string) $guardianProfileId);
        }

        // ليس معلمًا ولا طالبًا ولا ولي أمر — دور آخر (إداري بصلاحية
        // message.moderate مثلًا)؛ لا نقيّده هنا، القرار للمستدعي.
        return null;
    }

    /** @return list<string> */
    private function reachableForTeacher(string $organizationId, string $staffProfileId): array
    {
        $groupStudentIds = DB::table('group_teachers')
            ->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->join('group_memberships', 'group_memberships.group_id', '=', 'groups.id')
            ->join('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
            ->where('group_teachers.staff_profile_id', $staffProfileId)
            ->where('groups.organization_id', $organizationId)
            ->whereColumn('student_profiles.organization_id', 'groups.organization_id')
            ->whereNull('groups.deleted_at')
            ->whereNull('student_profiles.deleted_at')
            ->whereNull('group_memberships.left_at')
            ->where(function ($query): void {
                $query->whereNull('group_teachers.assigned_to')
                    ->orWhere('group_teachers.assigned_to', '>=', now('UTC')->toDateString());
            })
            ->pluck('student_profiles.user_id');

        $individualStudentIds = DB::table('schedules')
            ->join('student_profiles', 'student_profiles.id', '=', 'schedules.student_profile_id')
            ->where('schedules.staff_profile_id', $staffProfileId)
            ->where('schedules.organization_id', $organizationId)
            ->where('schedules.is_active', true)
            ->whereNotNull('schedules.student_profile_id')
            ->where('student_profiles.organization_id', $organizationId)
            ->whereNull('student_profiles.deleted_at')
            ->pluck('student_profiles.user_id');

        return $this->normalizeIds($groupStudentIds->merge($individualStudentIds));
    }

    /** @return list<string> */
    private function reachableForStudent(string $organizationId, string $studentProfileId): array
    {
        $groupIds = $this->activeGroupIdsForStudent($organizationId, $studentProfileId);

        $teacherUserIds = $this->teacherUserIdsForStudent($organizationId, $studentProfileId, $groupIds);
        $classmateStudentProfileIds = $this->classmateStudentProfileIds($organizationId, $groupIds, $studentProfileId);

        $classmateUserIds = DB::table('student_profiles')
            ->whereIn('id', $classmateStudentProfileIds)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        $classmateGuardianUserIds = $this->guardianUserIdsForStudents($organizationId, $classmateStudentProfileIds);

        return $this->normalizeIds(
            $teacherUserIds->merge($classmateUserIds)->merge($classmateGuardianUserIds),
        );
    }

    /** @return list<string> */
    private function reachableForGuardian(string $organizationId, string $guardianProfileId): array
    {
        $childStudentProfileIds = DB::table('guardian_links')
            ->where('guardian_profile_id', $guardianProfileId)
            ->whereNotNull('verified_at')
            ->whereNull('deleted_at')
            ->pluck('student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id);

        $teacherUserIds = Collection::make();
        foreach ($childStudentProfileIds as $childStudentProfileId) {
            $groupIds = $this->activeGroupIdsForStudent($organizationId, $childStudentProfileId);
            $teacherUserIds = $teacherUserIds->merge(
                $this->teacherUserIdsForStudent($organizationId, $childStudentProfileId, $groupIds),
            );
        }

        return $this->normalizeIds($teacherUserIds);
    }

    /** @return Collection<int, string> */
    private function activeGroupIdsForStudent(string $organizationId, string $studentProfileId): Collection
    {
        return DB::table('group_memberships')
            ->join('groups', 'groups.id', '=', 'group_memberships.group_id')
            ->where('group_memberships.student_profile_id', $studentProfileId)
            ->where('group_memberships.status', 'active')
            ->whereNull('group_memberships.left_at')
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->pluck('groups.id')
            ->map(static fn (mixed $id): string => (string) $id);
    }

    /**
     * @param Collection<int, string> $groupIds
     * @return Collection<int, string>
     */
    private function teacherUserIdsForStudent(
        string $organizationId,
        string $studentProfileId,
        Collection $groupIds,
    ): Collection {
        $groupTeacherUserIds = $groupIds->isEmpty()
            ? Collection::make()
            : DB::table('group_teachers')
                ->join('staff_profiles', 'staff_profiles.id', '=', 'group_teachers.staff_profile_id')
                ->whereIn('group_teachers.group_id', $groupIds->all())
                ->where('staff_profiles.organization_id', $organizationId)
                ->whereNull('staff_profiles.deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('group_teachers.assigned_to')
                        ->orWhere('group_teachers.assigned_to', '>=', now('UTC')->toDateString());
                })
                ->pluck('staff_profiles.user_id');

        $individualTeacherUserIds = DB::table('schedules')
            ->join('staff_profiles', 'staff_profiles.id', '=', 'schedules.staff_profile_id')
            ->where('schedules.student_profile_id', $studentProfileId)
            ->where('schedules.organization_id', $organizationId)
            ->where('schedules.is_active', true)
            ->where('staff_profiles.organization_id', $organizationId)
            ->whereNull('staff_profiles.deleted_at')
            ->pluck('staff_profiles.user_id');

        return $groupTeacherUserIds->merge($individualTeacherUserIds);
    }

    /**
     * @param Collection<int, string> $groupIds
     * @return Collection<int, string>
     */
    private function classmateStudentProfileIds(
        string $organizationId,
        Collection $groupIds,
        string $excludingStudentProfileId,
    ): Collection {
        if ($groupIds->isEmpty()) {
            return Collection::make();
        }

        return DB::table('group_memberships')
            ->whereIn('group_id', $groupIds->all())
            ->where('status', 'active')
            ->whereNull('left_at')
            ->where('student_profile_id', '!=', $excludingStudentProfileId)
            ->pluck('student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id);
    }

    /**
     * @param Collection<int, string> $studentProfileIds
     * @return Collection<int, string>
     */
    private function guardianUserIdsForStudents(string $organizationId, Collection $studentProfileIds): Collection
    {
        if ($studentProfileIds->isEmpty()) {
            return Collection::make();
        }

        return DB::table('guardian_links')
            ->join('guardian_profiles', 'guardian_profiles.id', '=', 'guardian_links.guardian_profile_id')
            ->whereIn('guardian_links.student_profile_id', $studentProfileIds->all())
            ->whereNotNull('guardian_links.verified_at')
            ->whereNull('guardian_links.deleted_at')
            ->where('guardian_profiles.organization_id', $organizationId)
            ->whereNull('guardian_profiles.deleted_at')
            ->pluck('guardian_profiles.user_id');
    }

    /**
     * @param Collection<int, mixed> $ids
     * @return list<string>
     */
    private function normalizeIds(Collection $ids): array
    {
        return $ids
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->map(static fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
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
