<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;

/**
 * Platform composition for phase-one events whose owners publish profile,
 * enrollment, group or assignment IDs rather than Identity user IDs.
 */
final readonly class PhaseOneDomainEventRecipientResolver implements DomainEventRecipientResolver
{
    public function resolve(
        string $eventKey,
        array $audiences,
        array $recipientFields,
        array $payload,
    ): array {
        $organizationId = $payload['organization_id'] ?? null;

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        $ids = $this->payloadUserIds($recipientFields, $payload);
        $studentProfileIds = $this->stringValues($payload, ['student_profile_id', 'student_profile_ids']);
        $staffProfileIds = $this->stringValues($payload, ['staff_profile_id', 'staff_profile_ids']);

        $enrollmentId = $payload['enrollment_id'] ?? null;
        if (is_string($enrollmentId) && $enrollmentId !== '') {
            $studentProfileId = DB::table('enrollments')
                ->where('id', $enrollmentId)
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->value('student_profile_id');

            if (is_string($studentProfileId)) {
                $studentProfileIds[] = $studentProfileId;
            }
        }

        $assignmentId = $payload['assignment_id'] ?? null;
        if (is_string($assignmentId) && $assignmentId !== '') {
            $assignment = DB::table('assignments')
                ->where('id', $assignmentId)
                ->where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->first(['staff_profile_id']);

            if ($assignment !== null && is_string($assignment->staff_profile_id ?? null)) {
                $staffProfileIds[] = $assignment->staff_profile_id;
            }
        }

        $sessionId = $payload['session_id'] ?? null;
        if (is_string($sessionId) && $sessionId !== '') {
            $studentProfileIds = [
                ...$studentProfileIds,
                ...DB::table('session_participants')
                    ->join('sessions', 'sessions.id', '=', 'session_participants.session_id')
                    ->where('session_participants.session_id', $sessionId)
                    ->where('sessions.organization_id', $organizationId)
                    ->whereNull('sessions.deleted_at')
                    ->pluck('session_participants.student_profile_id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all(),
            ];
        }

        $groupId = $payload['group_id'] ?? null;
        if (is_string($groupId) && $groupId !== '') {
            if ($this->hasAnyAudience($audiences, ['student', 'guardian'])) {
                $studentProfileIds = [
                    ...$studentProfileIds,
                    ...$this->activeGroupStudentProfileIds($organizationId, $groupId),
                ];
            }

            if (in_array('teacher', $audiences, true)) {
                $staffProfileIds = [
                    ...$staffProfileIds,
                    ...$this->currentGroupStaffProfileIds($organizationId, $groupId),
                ];
            }
        }

        $courseId = $payload['course_id'] ?? null;
        if (($groupId === null || $groupId === '')
            && is_string($courseId)
            && $courseId !== ''
            && $this->hasAnyAudience($audiences, ['student', 'guardian'])) {
            $studentProfileIds = [
                ...$studentProfileIds,
                ...$this->activeCourseStudentProfileIds($organizationId, $courseId),
            ];
        }

        $studentProfileIds = array_values(array_unique($studentProfileIds));
        $staffProfileIds = array_values(array_unique($staffProfileIds));

        if (in_array('student', $audiences, true) && $studentProfileIds !== []) {
            $ids = [
                ...$ids,
                ...DB::table('student_profiles')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->whereIn('id', $studentProfileIds)
                    ->pluck('user_id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all(),
            ];
        }

        if (in_array('guardian', $audiences, true) && $studentProfileIds !== []) {
            $ids = [
                ...$ids,
                ...DB::table('guardian_links')
                    ->join('guardian_profiles', 'guardian_profiles.id', '=', 'guardian_links.guardian_profile_id')
                    ->whereIn('guardian_links.student_profile_id', $studentProfileIds)
                    ->whereNotNull('guardian_links.verified_at')
                    ->where('guardian_profiles.organization_id', $organizationId)
                    ->whereNull('guardian_profiles.deleted_at')
                    ->pluck('guardian_profiles.user_id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all(),
            ];
        }

        if (in_array('teacher', $audiences, true) && $staffProfileIds !== []) {
            $ids = [
                ...$ids,
                ...DB::table('staff_profiles')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->whereIn('id', $staffProfileIds)
                    ->pluck('user_id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->all(),
            ];
        }

        foreach (['supervisor', 'admin'] as $audience) {
            if (!in_array($audience, $audiences, true)) {
                continue;
            }

            $permissions = array_values(array_filter(
                (array) config("notifications.audience_permissions.{$audience}", []),
                static fn (mixed $permission): bool => is_string($permission) && $permission !== '',
            ));
            $ids = [...$ids, ...$this->usersWithAnyPermission($organizationId, $permissions)];
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return DB::table('users')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function payloadUserIds(array $recipientFields, array $payload): array
    {
        $ids = [];

        foreach (array_unique([...$recipientFields, 'recipient_user_id', 'recipient_user_ids']) as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $value = data_get($payload, $field);
            foreach (is_array($value) ? $value : [$value] as $id) {
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<string> $keys @return list<string> */
    private function stringValues(array $payload, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_string($item) && $item !== '') {
                    $values[] = $item;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /** @param list<string> $expected */
    private function hasAnyAudience(array $audiences, array $expected): bool
    {
        return array_intersect($audiences, $expected) !== [];
    }

    /** @return list<string> */
    private function activeGroupStudentProfileIds(string $organizationId, string $groupId): array
    {
        return DB::table('group_memberships')
            ->join('student_profiles', 'student_profiles.id', '=', 'group_memberships.student_profile_id')
            ->join('groups', 'groups.id', '=', 'group_memberships.group_id')
            ->where('group_memberships.group_id', $groupId)
            ->where('group_memberships.status', 'active')
            ->whereNull('group_memberships.left_at')
            ->where('student_profiles.organization_id', $organizationId)
            ->whereNull('student_profiles.deleted_at')
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->whereNotExists(function (Builder $query) use ($organizationId): void {
                $query->selectRaw('1')
                    ->from('group_programs')
                    ->join('enrollments', 'enrollments.program_id', '=', 'group_programs.program_id')
                    ->whereColumn('group_programs.group_id', 'group_memberships.group_id')
                    ->whereColumn('enrollments.student_profile_id', 'group_memberships.student_profile_id')
                    ->where('enrollments.organization_id', $organizationId)
                    ->whereNull('enrollments.deleted_at')
                    ->where('enrollments.status', '!=', 'active');
            })
            ->distinct()
            ->pluck('group_memberships.student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /** @return list<string> */
    private function currentGroupStaffProfileIds(string $organizationId, string $groupId): array
    {
        $today = now('UTC')->toDateString();

        return DB::table('group_teachers')
            ->join('groups', 'groups.id', '=', 'group_teachers.group_id')
            ->where('group_teachers.group_id', $groupId)
            ->where('groups.organization_id', $organizationId)
            ->whereNull('groups.deleted_at')
            ->whereDate('group_teachers.assigned_from', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('group_teachers.assigned_to')
                    ->orWhereDate('group_teachers.assigned_to', '>=', $today);
            })
            ->distinct()
            ->pluck('group_teachers.staff_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /** @return list<string> */
    private function activeCourseStudentProfileIds(string $organizationId, string $courseId): array
    {
        return DB::table('courses')
            ->join('levels', 'levels.id', '=', 'courses.level_id')
            ->join('enrollments', 'enrollments.program_id', '=', 'levels.program_id')
            ->where('courses.id', $courseId)
            ->where('courses.organization_id', $organizationId)
            ->where('enrollments.organization_id', $organizationId)
            ->where('enrollments.status', 'active')
            ->whereNull('enrollments.deleted_at')
            ->distinct()
            ->pluck('enrollments.student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /** @param list<string> $permissions @return list<string> */
    private function usersWithAnyPermission(string $organizationId, array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }

        $modelType = (string) config('auth.providers.users.model');

        return DB::table('users')
            ->where('users.organization_id', $organizationId)
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->where(function (Builder $permissionScope) use ($permissions, $modelType, $organizationId): void {
                $permissionScope->whereExists(function (Builder $direct) use ($permissions, $modelType): void {
                    $direct->selectRaw('1')
                        ->from('model_has_permissions')
                        ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                        ->whereColumn('model_has_permissions.model_id', 'users.id')
                        ->where('model_has_permissions.model_type', $modelType)
                        ->whereIn('permissions.name', $permissions);
                })->orWhereExists(function (Builder $role) use ($permissions, $modelType, $organizationId): void {
                    $role->selectRaw('1')
                        ->from('model_has_roles')
                        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                        ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
                        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                        ->whereColumn('model_has_roles.model_id', 'users.id')
                        ->where('model_has_roles.model_type', $modelType)
                        ->where(function (Builder $tenantRole) use ($organizationId): void {
                            $tenantRole->whereNull('roles.organization_id')
                                ->orWhere('roles.organization_id', $organizationId);
                        })
                        ->whereIn('permissions.name', $permissions);
                });
            })
            ->pluck('users.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }
}
