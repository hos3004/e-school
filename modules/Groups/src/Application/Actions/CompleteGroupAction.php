<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
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
    ) {}

    public function execute(Group $group): Group
    {
        $this->assertNotArchived($group);
        $this->assertTransitionAllowed($group);

        $group = $this->transaction->run(function () use ($group): Group {
            $group->status = GroupStatus::Completed;
            $group->save();

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
