<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Events\TeacherAssignedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إسناد معلم إلى مجموعة: لا إسناد لمجموعة مُختمة، ولا تكرار لنفس المعلم
 * على نفس المقرر داخل المجموعة (قيّد فهرس فريد في قاعدة البيانات).
 */
final readonly class AssignTeacherAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
        private AcademicCatalogQueries $academics,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(Group $group, array $data, ?string $actorId = null, ?string $reason = null): GroupTeacher
    {
        $staffProfileId = (string) $data['staff_profile_id'];
        $courseId = isset($data['course_id']) ? (string) $data['course_id'] : null;
        $role = $data['role'] instanceof GroupTeacherRole
            ? $data['role']
            : GroupTeacherRole::from((string) $data['role']);

        $this->assertNotArchived($group);
        $this->assertGroupMutable($group);
        $this->assertTeacherAndCourseBelongToOrganization($group, $staffProfileId, $courseId);
        $this->assertNotAlreadyAssigned($group, $staffProfileId, $courseId);

        $assignment = $this->transaction->run(function () use ($group, $data, $staffProfileId, $courseId, $role, $actorId, $reason): GroupTeacher {
            $assignment = new GroupTeacher;
            $assignment->fill([
                'group_id' => (string) $group->getKey(),
                'staff_profile_id' => $staffProfileId,
                'course_id' => $courseId,
                'role' => $role,
                'assigned_from' => $data['assigned_from'],
                'assigned_to' => $data['assigned_to'] ?? null,
            ]);
            $assignment->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.teacher_assigned',
                    auditableType: 'group_teachers',
                    auditableId: (string) $assignment->getKey(),
                    oldValues: null,
                    newValues: [
                        'group_id' => $group->getKey(),
                        'staff_profile_id' => $staffProfileId,
                        'course_id' => $courseId,
                        'role' => $role->value,
                    ],
                    reason: trim($reason),
                );
            }

            return $assignment;
        });

        $this->events->dispatch(new TeacherAssignedToGroup(
            assignmentId: (string) $assignment->getKey(),
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
            staffProfileId: $staffProfileId,
            courseId: $courseId,
            role: $role->value,
        ));

        return $assignment;
    }

    private function assertNotArchived(Group $group): void
    {
        if ($group->trashed()) {
            throw BusinessRuleViolation::make(
                'groups.already_archived',
                'groups::errors.already_archived',
                ['group_id' => (string) $group->getKey()],
            );
        }
    }

    private function assertGroupMutable(Group $group): void
    {
        if (!$group->status->acceptsMembers()) {
            throw BusinessRuleViolation::make(
                'groups.group_not_open',
                'groups::errors.group_not_accepting_members',
                ['status' => $group->status->label()],
            );
        }
    }

    private function assertNotAlreadyAssigned(Group $group, string $staffProfileId, ?string $courseId): void
    {
        $query = GroupTeacher::query()
            ->where('group_id', (string) $group->getKey())
            ->where('staff_profile_id', $staffProfileId);

        $exists = $courseId === null
            ? (clone $query)->whereNull('course_id')->exists()
            : (clone $query)->where('course_id', $courseId)->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'groups.teacher_already_assigned',
                'groups::errors.teacher_already_assigned',
                ['staff_profile_id' => $staffProfileId],
            );
        }
    }

    private function assertTeacherAndCourseBelongToOrganization(
        Group $group,
        string $staffProfileId,
        ?string $courseId,
    ): void {
        $organizationId = (string) $group->organization_id;

        if ($this->staff->userIdForProfile($organizationId, $staffProfileId) === null) {
            throw BusinessRuleViolation::make(
                'groups.teacher_profile_invalid',
                'groups::errors.teacher_profile_invalid',
            );
        }

        if ($courseId === null) {
            return;
        }

        if (!isset($this->academics->coursesByIds($organizationId, [$courseId])[$courseId])) {
            throw BusinessRuleViolation::make(
                'groups.course_not_found',
                'groups::errors.course_not_found',
            );
        }

        if (!$this->qualifications->isQualified($staffProfileId, $courseId)) {
            throw BusinessRuleViolation::make(
                'groups.teacher_not_qualified',
                'groups::errors.teacher_not_qualified',
                ['course_id' => $courseId],
            );
        }
    }
}
