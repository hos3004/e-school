<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Events\ProgramDetachedFromGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * فك ربط برنامج عن مجموعة: إزالة رابط الجدول الوسيط فقط،
 * ولا يمس بيانات المجموعة أو البرنامج نفسه.
 */
final readonly class DetachProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(GroupProgram $link, string $reason, ?string $actorId = null): void
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'groups.reason_required',
                'groups::errors.detach_reason_required',
            );
        }
        $groupId = (string) $link->group_id;
        $programId = (string) $link->program_id;

        /** @var Group $group */
        $group = $link->group()->firstOrFail();

        $this->transaction->run(function () use ($link, $group, $groupId, $programId, $reason, $actorId): void {
            $link->delete();

            if ($actorId !== null) {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.program_detached',
                    auditableType: 'group_programs',
                    auditableId: (string) $link->getKey(),
                    oldValues: [
                        'group_id' => $groupId,
                        'program_id' => $programId,
                    ],
                    newValues: null,
                    reason: trim($reason),
                );
            }
        });

        $this->events->dispatch(new ProgramDetachedFromGroup(
            groupId: $groupId,
            organizationId: (string) $group->organization_id,
            programId: $programId,
        ));
    }
}
