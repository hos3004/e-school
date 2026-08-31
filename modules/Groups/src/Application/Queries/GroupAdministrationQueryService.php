<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Groups\Domain\ValueObjects\GroupMemberData;
use Modules\Groups\Domain\ValueObjects\PlacementGroupData;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Groups\Domain\ValueObjects\StudentGroupMembershipData;
use Modules\Groups\Domain\ValueObjects\TeacherGroupAssignmentData;

final readonly class GroupAdministrationQueryService implements GroupAdministrationQueries
{
    public function groupsByIds(string $organizationId, array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter(
            $groupIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($groupIds === []) {
            return [];
        }

        return Group::query()
            ->forOrganization($organizationId)
            ->whereKey($groupIds)
            ->with(['programs', 'teachers'])
            ->get()
            ->mapWithKeys(static fn (Group $group): array => [
                (string) $group->getKey() => self::schedulingData($group),
            ])
            ->all();
    }

    public function activeGroupsForScheduling(string $organizationId): array
    {
        $today = now('UTC')->toDateString();

        return Group::query()
            ->forOrganization($organizationId)
            ->withStatus(GroupStatus::Active)
            ->with([
                'programs',
                'teachers' => static fn ($query) => $query
                    ->whereDate('assigned_from', '<=', $today)
                    ->where(static fn ($assignment) => $assignment
                        ->whereNull('assigned_to')
                        ->orWhereDate('assigned_to', '>=', $today)),
            ])
            ->orderBy('code')
            ->get()
            ->map(static fn (Group $group): SchedulingGroupData => self::schedulingData($group))
            ->values()
            ->all();
    }

    public function availableForPlacement(
        string $organizationId,
        string $programId,
        string $courseId,
    ): array {
        $groups = Group::query()
            ->forOrganization($organizationId)
            ->withStatus(GroupStatus::Active)
            ->whereHas('programs', static fn (Builder $query): Builder => $query->where('program_id', $programId))
            ->whereHas('teachers', static fn (Builder $query): Builder => $query
                ->where('course_id', $courseId)
                ->whereNull('assigned_to'))
            ->withCount(['memberships as occupied_seats_count' => static fn (Builder $query): Builder => $query
                ->whereNull('left_at')])
            ->with(['teachers' => static function ($query) use ($courseId): void {
                $query->where('course_id', $courseId)->whereNull('assigned_to');
            }])
            ->orderBy('code')
            ->get();

        return $groups
            ->filter(static fn (Group $group): bool => (int) $group->occupied_seats_count < $group->effectiveCapacity())
            ->map(static fn (Group $group): PlacementGroupData => self::placementData($group))
            ->values()
            ->all();
    }

    public function openForPlacement(
        string $organizationId,
        string $programId,
        ?string $courseId,
    ): array {
        $groups = Group::query()
            ->forOrganization($organizationId)
            ->whereIn('status', [GroupStatus::Planning, GroupStatus::Active])
            ->whereHas('programs', static fn (Builder $query): Builder => $query->where('program_id', $programId))
            /*
             * المجموعة النشطة لا تقبل تسكينًا في كورس بلا معلم مُسند — نفس شرط
             * GroupPlacementService. المجموعة قيد التخطيط معفاة لأن المعلم من
             * البيانات المؤجَّلة التي تُستوفى عند التفعيل.
             */
            ->when($courseId !== null, static fn (Builder $query): Builder => $query->where(
                static fn (Builder $nested): Builder => $nested
                    ->where('status', GroupStatus::Planning)
                    ->orWhereHas('teachers', static fn (Builder $teachers): Builder => $teachers
                        ->where('course_id', $courseId)
                        ->whereNull('assigned_to')),
            ))
            ->withCount(['memberships as occupied_seats_count' => static fn (Builder $query): Builder => $query
                ->whereNull('left_at')])
            ->with(['teachers' => static function ($query) use ($courseId): void {
                $query->whereNull('assigned_to');

                if ($courseId !== null) {
                    $query->where('course_id', $courseId);
                }
            }])
            ->orderBy('status')
            ->orderBy('code')
            ->get();

        return $groups
            ->filter(static fn (Group $group): bool => (int) $group->occupied_seats_count < $group->effectiveCapacity())
            ->map(static fn (Group $group): PlacementGroupData => self::placementData($group))
            ->values()
            ->all();
    }

    public function openGroupForPlacement(string $organizationId, string $groupId): ?PlacementGroupData
    {
        if ($organizationId === '' || $groupId === '') {
            return null;
        }

        /** @var Group|null $group */
        $group = Group::query()
            ->forOrganization($organizationId)
            ->whereKey($groupId)
            ->whereIn('status', [GroupStatus::Planning, GroupStatus::Active])
            ->withCount(['memberships as occupied_seats_count' => static fn (Builder $query): Builder => $query
                ->whereNull('left_at')])
            ->with(['teachers' => static fn ($query) => $query->whereNull('assigned_to')])
            ->first();

        return $group === null ? null : self::placementData($group);
    }

    private static function placementData(Group $group): PlacementGroupData
    {
        $occupied = (int) $group->occupied_seats_count;

        return new PlacementGroupData(
            id: (string) $group->getKey(),
            code: (string) $group->code,
            name: is_array($group->name) ? $group->name : [],
            capacity: $group->capacity === null ? null : (int) $group->capacity,
            occupiedSeats: $occupied,
            teacherProfileIds: $group->teachers
                ->pluck('staff_profile_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all(),
            status: $group->status->value,
            remainingSeats: max(0, $group->effectiveCapacity() - $occupied),
            startsOn: $group->starts_on?->toDateString(),
            endsOn: $group->ends_on?->toDateString(),
        );
    }

    public function membershipsForStudent(string $organizationId, string $studentProfileId): array
    {
        return GroupMembership::query()
            ->forStudent($studentProfileId)
            ->whereHas('group', static fn (Builder $query): Builder => $query
                ->where('organization_id', $organizationId))
            ->with('group')
            ->latest('joined_at')
            ->get()
            ->map(static fn (GroupMembership $membership): StudentGroupMembershipData => new StudentGroupMembershipData(
                membershipId: (string) $membership->getKey(),
                groupId: (string) $membership->group_id,
                groupCode: (string) $membership->group?->code,
                groupName: is_array($membership->group?->name) ? $membership->group->name : [],
                groupStatus: $membership->group?->status->value ?? '',
                membershipStatus: $membership->status->value,
                joinedAt: $membership->joined_at?->toIso8601String(),
                leftAt: $membership->left_at?->toIso8601String(),
            ))
            ->values()
            ->all();
    }

    public function assignmentsForTeacher(string $organizationId, string $staffProfileId): array
    {
        return GroupTeacher::query()
            ->forStaff($staffProfileId)
            ->whereHas('group', static fn (Builder $query): Builder => $query
                ->where('organization_id', $organizationId))
            ->with('group')
            ->latest('assigned_from')
            ->get()
            ->map(static fn (GroupTeacher $assignment): TeacherGroupAssignmentData => new TeacherGroupAssignmentData(
                assignmentId: (string) $assignment->getKey(),
                staffProfileId: (string) $assignment->staff_profile_id,
                groupId: (string) $assignment->group_id,
                groupCode: (string) $assignment->group?->code,
                groupName: is_array($assignment->group?->name) ? $assignment->group->name : [],
                groupStatus: $assignment->group?->status->value ?? '',
                courseId: $assignment->course_id === null ? null : (string) $assignment->course_id,
                role: $assignment->role->value,
                assignedFrom: $assignment->assigned_from?->toDateString(),
                assignedTo: $assignment->assigned_to?->toDateString(),
            ))
            ->values()
            ->all();
    }

    public function programIdsForGroup(string $organizationId, string $groupId): array
    {
        return Group::query()
            ->forOrganization($organizationId)
            ->whereKey($groupId)
            ->first()?->programs()
            ->pluck('program_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all() ?? [];
    }

    public function membershipsForGroup(string $organizationId, string $groupId): array
    {
        if (!$this->groupExists($organizationId, $groupId)) {
            return [];
        }

        return GroupMembership::query()
            ->forGroup($groupId)
            ->latest('joined_at')
            ->get()
            ->map(static fn (GroupMembership $membership): GroupMemberData => new GroupMemberData(
                membershipId: (string) $membership->getKey(),
                studentProfileId: (string) $membership->student_profile_id,
                status: $membership->status->value,
                joinedAt: $membership->joined_at?->toIso8601String(),
                leftAt: $membership->left_at?->toIso8601String(),
            ))
            ->values()
            ->all();
    }

    public function assignmentsForGroup(string $organizationId, string $groupId): array
    {
        if (!$this->groupExists($organizationId, $groupId)) {
            return [];
        }

        return GroupTeacher::query()
            ->forGroup($groupId)
            ->with('group')
            ->latest('assigned_from')
            ->get()
            ->map(static fn (GroupTeacher $assignment): TeacherGroupAssignmentData => new TeacherGroupAssignmentData(
                assignmentId: (string) $assignment->getKey(),
                staffProfileId: (string) $assignment->staff_profile_id,
                groupId: (string) $assignment->group_id,
                groupCode: (string) $assignment->group?->code,
                groupName: is_array($assignment->group?->name) ? $assignment->group->name : [],
                groupStatus: $assignment->group?->status->value ?? '',
                courseId: $assignment->course_id === null ? null : (string) $assignment->course_id,
                role: $assignment->role->value,
                assignedFrom: $assignment->assigned_from?->toDateString(),
                assignedTo: $assignment->assigned_to?->toDateString(),
            ))
            ->values()
            ->all();
    }

    public function activeAssignmentCountsForTeachers(string $organizationId, array $staffProfileIds): array
    {
        $staffProfileIds = array_values(array_unique(array_filter(
            $staffProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($staffProfileIds === []) {
            return [];
        }

        return GroupTeacher::query()
            ->whereIn('staff_profile_id', $staffProfileIds)
            ->whereNull('assigned_to')
            ->whereHas('group', static fn (Builder $query): Builder => $query
                ->where('organization_id', $organizationId)
                ->where('status', GroupStatus::Active->value))
            ->groupBy('staff_profile_id')
            ->selectRaw('staff_profile_id, count(*) as assignments_count')
            ->pluck('assignments_count', 'staff_profile_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    private function groupExists(string $organizationId, string $groupId): bool
    {
        return Group::query()
            ->forOrganization($organizationId)
            ->whereKey($groupId)
            ->exists();
    }

    private static function schedulingData(Group $group): SchedulingGroupData
    {
        return new SchedulingGroupData(
            id: (string) $group->getKey(),
            code: (string) $group->code,
            name: is_array($group->name) ? $group->name : [],
            status: $group->status->value,
            timezone: (string) $group->timezone,
            startsOn: $group->starts_on?->toDateString(),
            endsOn: $group->ends_on?->toDateString(),
            programIds: $group->programs
                ->pluck('program_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all(),
            teacherAssignments: $group->teachers
                ->map(static fn ($assignment): TeacherGroupAssignmentData => new TeacherGroupAssignmentData(
                    assignmentId: (string) $assignment->getKey(),
                    staffProfileId: (string) $assignment->staff_profile_id,
                    groupId: (string) $group->getKey(),
                    groupCode: (string) $group->code,
                    groupName: is_array($group->name) ? $group->name : [],
                    groupStatus: $group->status->value,
                    courseId: $assignment->course_id === null ? null : (string) $assignment->course_id,
                    role: $assignment->role->value,
                    assignedFrom: $assignment->assigned_from?->toDateString(),
                    assignedTo: $assignment->assigned_to?->toDateString(),
                ))
                ->values()
                ->all(),
        );
    }
}
