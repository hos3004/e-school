<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Shared\Support\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Groups\Domain\Events\GroupArchived;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;

/**
 * أرشفة مجموعة: تعليق وصولها دون حذف بياناتها (SoftDeletes) مع تسجيل السبب.
 */
final readonly class ArchiveGroupAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Group $group, string $reason): Group
    {
        $this->assertReasonGiven($reason);
        $this->assertNotArchived($group);

        $this->transaction->run(function () use ($group): void {
            $group->delete();
        });

        $this->events->dispatch(new GroupArchived(
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
            reason: trim($reason),
        ));

        return $group;
    }

    private function assertReasonGiven(string $reason): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'groups.reason_required',
                'groups::errors.archive_reason_required',
            );
        }
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
}
