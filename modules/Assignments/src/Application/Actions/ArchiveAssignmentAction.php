<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ArchiveAssignmentAction
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(Assignment $assignment, string $actorId, string $reason): void
    {
        if ($assignment->submissions()
            ->whereIn('status', [
                AssignmentSubmissionStatus::Submitted->value,
                AssignmentSubmissionStatus::Late->value,
            ])->exists()) {
            throw BusinessRuleViolation::make(
                'assignments.ungraded_submissions',
                'assignments::errors.ungraded_submissions',
            );
        }

        $this->transaction->run(function () use ($assignment, $actorId, $reason): void {
            $assignment->delete();

            $this->audit->record(
                organizationId: (string) $assignment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assignments.archived',
                auditableType: 'assignment',
                auditableId: (string) $assignment->getKey(),
                oldValues: ['deleted_at' => null],
                newValues: ['deleted_at' => $assignment->deleted_at?->toIso8601String()],
                reason: $reason,
            );
        });
    }
}
