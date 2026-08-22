<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
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
    ) {}

    public function execute(GroupTeacher $assignment): GroupTeacher
    {
        $this->assertStillOpen($assignment);

        $assignment = $this->transaction->run(function () use ($assignment): GroupTeacher {
            $assignment->assigned_to = CarbonImmutable::now('UTC')->toDateString();
            $assignment->save();

            return $assignment;
        });

        /** @var Group $group */
        $group = $assignment->group()->firstOrFail();

        $this->events->dispatch(new TeacherUnassignedFromGroup(
            assignmentId: (string) $assignment->getKey(),
            groupId: (string) $assignment->group_id,
            organizationId: (string) $group->organization_id,
            staffProfileId: (string) $assignment->staff_profile_id,
        ));

        return $assignment;
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
