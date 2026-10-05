<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\QuestionRemoved;
use Modules\Assessments\Domain\Models\Question;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * حذف سؤال — يُمنع إذا سبق أن سلّم أي طالب محاولته على الاختبار.
 */
final readonly class RemoveQuestionAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(Question $question, string $actorId, string $reason): void
    {
        $assessment = $question->assessment;

        if ($assessment !== null && $assessment->attempts()->exists()) {
            throw BusinessRuleViolation::make(
                'assessments.edit_after_attempts',
                'assessments::errors.edit_after_attempts',
            );
        }

        $this->transaction->run(function () use ($question, $assessment, $actorId, $reason): void {
            $oldValues = $question->only(['id', 'type', 'score', 'sort_order']);
            $question->delete();

            $this->audit->record(
                organizationId: (string) $assessment?->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.question_removed',
                auditableType: 'assessment',
                auditableId: (string) $question->assessment_id,
                oldValues: $oldValues,
                newValues: null,
                reason: $reason,
            );
        });

        $this->events->dispatch(new QuestionRemoved(
            assessmentId: (string) $question->assessment_id,
            organizationId: (string) $assessment?->organization_id,
            questionId: $question->id,
            actorId: $actorId,
        ));
    }
}
