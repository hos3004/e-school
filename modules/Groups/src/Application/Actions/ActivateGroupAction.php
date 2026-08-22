<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Shared\Support\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Events\GroupActivated;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;

/**
 * تفعيل مجموعة «قيد التخطيط» لتصبح جاهزة لاستقبال الطلاب والمعلمين.
 */
final readonly class ActivateGroupAction
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
            $group->status = GroupStatus::Active;
            $group->save();

            return $group;
        });

        $this->events->dispatch(new GroupActivated(
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
        if (! $group->status->canTransitionTo(GroupStatus::Active)) {
            throw BusinessRuleViolation::make(
                'groups.invalid_status_transition',
                'groups::errors.invalid_status_transition',
                ['from' => $group->status->label(), 'to' => GroupStatus::Active->label()],
            );
        }
    }
}
