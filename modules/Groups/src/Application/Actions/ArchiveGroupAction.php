<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Events\GroupArchived;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * أرشفة مجموعة: تعليق وصولها دون حذف بياناتها (SoftDeletes) مع تسجيل السبب.
 */
final readonly class ArchiveGroupAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Group $group, string $reason, ?string $actorId = null): Group
    {
        $this->assertReasonGiven($reason);
        $this->assertNotArchived($group);

        $this->transaction->run(function () use ($group, $reason, $actorId): void {
            $group->delete();

            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.group_archived',
                    auditableType: 'groups',
                    auditableId: (string) $group->getKey(),
                    oldValues: ['archived_at' => null],
                    newValues: ['archived_at' => now()->utc()->toIso8601String()],
                    reason: trim($reason),
                );
            }
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
