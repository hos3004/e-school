<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Events\GroupCompleted;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إتمام مجموعة نشطة: حالة نهائية تُغلق التسجيل والإسناد نهائيًا.
 */
final readonly class CompleteGroupAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Group $group, ?string $actorId = null, ?string $reason = null): Group
    {
        $this->assertNotArchived($group);
        $this->assertTransitionAllowed($group);

        $group = $this->transaction->run(function () use ($group, $actorId, $reason): Group {
            $oldStatus = $group->status->value;
            $group->status = GroupStatus::Completed;
            $group->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.group_completed',
                    auditableType: 'groups',
                    auditableId: (string) $group->getKey(),
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => GroupStatus::Completed->value],
                    reason: trim($reason),
                );
            }

            return $group;
        });

        $this->events->dispatch(new GroupCompleted(
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
        ));

        return $group;
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

    private function assertTransitionAllowed(Group $group): void
    {
        if (!$group->status->canTransitionTo(GroupStatus::Completed)) {
            throw BusinessRuleViolation::make(
                'groups.invalid_status_transition',
                'groups::errors.invalid_status_transition',
                ['from' => $group->status->label(), 'to' => GroupStatus::Completed->label()],
            );
        }
    }
}
