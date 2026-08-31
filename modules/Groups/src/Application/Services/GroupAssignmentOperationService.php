<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Services;

use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Application\Actions\UnassignTeacherAction;
use Modules\Groups\Application\Actions\WithdrawStudentAction;
use Modules\Groups\Domain\Contracts\GroupAssignmentOperations;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupTeacher;
use Shared\Support\BusinessRuleViolation;

/**
 * تنفيذ عمليات الإسناد داخل موديول Groups — لا يُكشف نموذج خارجيًا.
 */
final readonly class GroupAssignmentOperationService implements GroupAssignmentOperations
{
    public function __construct(
        private AssignTeacherAction $assignTeacher,
        private UnassignTeacherAction $unassignTeacher,
        private WithdrawStudentAction $withdrawStudent,
    ) {}

    public function assignTeacher(
        string $organizationId,
        string $groupId,
        string $staffProfileId,
        ?string $courseId,
        string $role,
        ?string $assignedFrom,
        ?string $assignedTo,
        string $actorId,
        string $reason,
    ): string {
        /** @var Group $group */
        $group = Group::query()
            ->whereKey($groupId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $assignment = $this->assignTeacher->execute($group, [
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'role' => $role,
            'assigned_from' => $assignedFrom ?? now('UTC')->toDateString(),
            'assigned_to' => $assignedTo,
        ], $actorId, $reason);

        return (string) $assignment->getKey();
    }

    public function unassignTeacher(
        string $organizationId,
        string $assignmentId,
        string $actorId,
        string $reason,
    ): void {
        /** @var GroupTeacher|null $assignment */
        $assignment = GroupTeacher::query()->find($assignmentId);

        if ($assignment === null || !$this->belongsToOrganization((string) $assignment->group_id, $organizationId)) {
            throw BusinessRuleViolation::make(
                'groups.assignment_not_in_organization',
                'groups::errors.teacher_profile_invalid',
            );
        }

        $this->unassignTeacher->execute($assignment, $reason, $actorId);
    }

    public function withdrawStudent(
        string $organizationId,
        string $membershipId,
        string $actorId,
        string $reason,
    ): void {
        /** @var GroupMembership|null $membership */
        $membership = GroupMembership::query()->find($membershipId);

        if ($membership === null || !$this->belongsToOrganization((string) $membership->group_id, $organizationId)) {
            throw BusinessRuleViolation::make(
                'groups.membership_not_in_organization',
                'groups::errors.membership_not_active',
                ['membership_id' => $membershipId],
            );
        }

        $this->withdrawStudent->execute($membership, $reason, $actorId);
    }

    private function belongsToOrganization(string $groupId, string $organizationId): bool
    {
        return Group::query()
            ->whereKey($groupId)
            ->where('organization_id', $organizationId)
            ->exists();
    }
}
