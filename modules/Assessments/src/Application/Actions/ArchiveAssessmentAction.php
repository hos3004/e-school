<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\AssessmentArchived;
use Modules\Assessments\Domain\Models\Assessment;
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
    ) {}

    public function execute(Assessment $assessment, ?string $actorId = null): Assessment
    {
        if ($assessment->attempts()->exists()) {
            throw BusinessRuleViolation::make(
                'assessments.archive_with_attempts',
                'assessments::errors.archive_with_attempts',
            );
        }

        $this->transaction->run(fn (): bool => $assessment->delete());

        $this->events->dispatch(new AssessmentArchived(
            assessmentId: $assessment->id,
            organizationId: $assessment->organization_id,
            actorId: $actorId,
        ));

        return $assessment;
    }
}
