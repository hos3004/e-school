<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\AssessmentArchived;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * أرشفة اختبار (حذف ناعم) — تُمنع إذا كانت هناك محاولات مسجلة
 * حفاظًا على سلامة السجلات الأكاديمية.
 */
final readonly class ArchiveAssessmentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Assessment $assessment, string $actorId, string $reason): Assessment
    {
        if ($assessment->attempts()->exists()) {
            throw BusinessRuleViolation::make(
                'assessments.archive_with_attempts',
                'assessments::errors.archive_with_attempts',
            );
        }

        $this->transaction->run(function () use ($assessment, $actorId, $reason): void {
            $assessment->delete();

            $this->audit->record(
                organizationId: (string) $assessment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.archived',
                auditableType: 'assessment',
                auditableId: (string) $assessment->getKey(),
                oldValues: ['deleted_at' => null],
                newValues: ['deleted_at' => $assessment->deleted_at?->toIso8601String()],
                reason: $reason,
            );
        });

        $this->events->dispatch(new AssessmentArchived(
            assessmentId: $assessment->id,
            organizationId: $assessment->organization_id,
            actorId: $actorId,
        ));

        return $assessment;
    }
}
