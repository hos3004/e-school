<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Shared\Support\Transaction;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Groups\Domain\Events\ProgramDetachedFromGroup;
use Modules\Groups\Domain\Models\GroupProgram;
use Shared\Support\BusinessRuleViolation;

/**
 * فك ربط برنامج عن مجموعة: إزالة رابط الجدول الوسيط فقط،
 * ولا يمس بيانات المجموعة أو البرنامج نفسه.
 */
final readonly class DetachProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(GroupProgram $link): void
    {
        $groupId = (string) $link->group_id;
        $programId = (string) $link->program_id;

        /** @var \Modules\Groups\Domain\Models\Group $group */
        $group = $link->group()->firstOrFail();

        $this->transaction->run(function () use ($link): void {
            $link->delete();
        });

        $this->events->dispatch(new ProgramDetachedFromGroup(
            groupId: $groupId,
            organizationId: (string) $group->organization_id,
            programId: $programId,
        ));
    }
}
