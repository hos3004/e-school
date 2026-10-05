<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Domain\Models\Enrollment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ChangeEnrollmentLevelAction
{
    public function __construct(
        private AcademicCatalogQueries $academics,
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function execute(Enrollment $enrollment, string $levelId, string $reason, ?string $actorId = null): Enrollment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.transition_reason_required',
                'enrollments::errors.transition_reason_required',
            );
        }

        $level = $this->academics->levelsByIds(
            (string) $enrollment->organization_id,
            [$levelId],
        )[$levelId] ?? null;

        if ($level === null || $level->programId !== (string) $enrollment->program_id) {
            throw BusinessRuleViolation::make(
                'enrollments.level_outside_program',
                'enrollments::errors.level_outside_program',
            );
        }

        if ((string) $enrollment->current_level_id === $levelId) {
            throw BusinessRuleViolation::make(
                'enrollments.level_unchanged',
                'enrollments::errors.level_unchanged',
            );
        }

        return $this->transaction->run(function () use ($enrollment, $levelId, $reason, $actorId): Enrollment {
            $oldLevelId = $enrollment->current_level_id;
            $enrollment->current_level_id = $levelId;
            $enrollment->save();

            $this->audit->record(
                organizationId: (string) $enrollment->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'enrollments.level_changed',
                auditableType: 'enrollments',
                auditableId: (string) $enrollment->getKey(),
                oldValues: ['current_level_id' => $oldLevelId],
                newValues: ['current_level_id' => $levelId],
                reason: $reason,
            );

            return $enrollment->refresh();
        });
    }
}
