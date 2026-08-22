<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Shared\Support\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Groups\Domain\Events\ProgramAttachedToGroup;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\Group;
use Shared\Support\BusinessRuleViolation;

/**
 * إرفاق برنامج بمجموعة: لا تكرار لربط نفس البرنامج بنفس المجموعة.
 */
final readonly class AttachProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Group $group, string $programId): GroupProgram
    {
        $this->assertNotArchived($group);
        $this->assertNotAlreadyAttached($group, $programId);

        $link = $this->transaction->run(function () use ($group, $programId): GroupProgram {
            $link = new GroupProgram;
            $link->fill([
                'group_id' => (string) $group->getKey(),
                'program_id' => $programId,
            ]);
            $link->save();

            return $link;
        });

        $this->events->dispatch(new ProgramAttachedToGroup(
            groupId: (string) $group->getKey(),
            organizationId: (string) $group->organization_id,
            programId: $programId,
        ));

        return $link;
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

    private function assertNotAlreadyAttached(Group $group, string $programId): void
    {
        $exists = GroupProgram::query()
            ->where('group_id', (string) $group->getKey())
            ->where('program_id', $programId)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'groups.program_already_attached',
                'groups::errors.program_already_attached',
                ['program_id' => $programId],
            );
        }
    }
}
