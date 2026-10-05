<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Groups\Domain\Events\ProgramAttachedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إرفاق برنامج بمجموعة: لا تكرار لربط نفس البرنامج بنفس المجموعة.
 */
final readonly class AttachProgramAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AcademicCatalogQueries $academics,
        private AuditRecorder $audit,
    ) {}

    public function execute(Group $group, string $programId, ?string $actorId = null, ?string $reason = null): GroupProgram
    {
        $this->assertNotArchived($group);
        $this->assertProgramBelongsToOrganization($group, $programId);
        $this->assertNotAlreadyAttached($group, $programId);

        $link = $this->transaction->run(function () use ($group, $programId, $actorId, $reason): GroupProgram {
            $link = new GroupProgram;
            $link->fill([
                'group_id' => (string) $group->getKey(),
                'program_id' => $programId,
            ]);
            $link->save();

            if ($actorId !== null && $reason !== null && trim($reason) !== '') {
                $this->audit->record(
                    organizationId: (string) $group->organization_id,
                    actorId: $actorId,
                    actorType: 'user',
                    action: 'groups.program_attached',
                    auditableType: 'group_programs',
                    auditableId: (string) $link->getKey(),
                    oldValues: null,
                    newValues: [
                        'group_id' => $group->getKey(),
                        'program_id' => $programId,
                    ],
                    reason: trim($reason),
                );
            }

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

    private function assertProgramBelongsToOrganization(Group $group, string $programId): void
    {
        if (!isset($this->academics->programsByIds((string) $group->organization_id, [$programId])[$programId])) {
            throw BusinessRuleViolation::make(
                'groups.program_not_found',
                'groups::errors.program_not_found',
            );
        }
    }
}
