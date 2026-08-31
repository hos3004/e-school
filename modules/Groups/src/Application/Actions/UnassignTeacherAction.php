<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Events\TeacherUnassignedFromGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إلغاء إسناد معلم عن مجموعة: تثبيت تاريخ نهاية الإسناد دون حذف السجل.
 */
final readonly class UnassignTeacherAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(GroupTeacher $assignment, string $reason, ?string $actorId = null): GroupTeacher
    {
        $this->assertReasonGiven($reason);
        $this->assertStillOpen($assignment);

        /** @var Group $group */
        $group = $assignment->group()->firstOrFail();

        $assignment = $this->transaction->run(function () use ($assignment, $group, $reason, $actorId): GroupTeacher {
            $assignment->assigned_to = CarbonImmutable::now('UTC');
            $assignment->save();

            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.teacher_unassigned',
                    auditableType: 'group_teachers',
                    auditableId: (string) $assignment->getKey(),
                    oldValues: ['assigned_to' => null],
                    newValues: ['assigned_to' => $assignment->assigned_to->toDateString()],
                    reason: trim($reason),
                );
            }

            return $assignment;
        });

        $this->events->dispatch(new TeacherUnassignedFromGroup(
            assignmentId: (string) $assignment->getKey(),
            groupId: (string) $assignment->group_id,
            organizationId: (string) $group->organization_id,
            staffProfileId: (string) $assignment->staff_profile_id,
        ));

        return $assignment;
    }

    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'groups.reason_required',
                'groups::errors.unassign_reason_required',
            );
        }
    }

    private function assertStillOpen(GroupTeacher $assignment): void
    {
        if (!$assignment->isOpen()) {
            throw BusinessRuleViolation::make(
                'groups.assignment_already_closed',
                'groups::errors.assignment_already_closed',
                ['assignment_id' => (string) $assignment->getKey()],
            );
        }
    }
}
