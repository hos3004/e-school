<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\QuestionRemoved;
use Modules\Assessments\Domain\Models\Question;
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
    ) {}

    public function execute(Question $question, ?string $actorId = null): void
    {
        $assessment = $question->assessment;

        if ($assessment !== null && $assessment->attempts()->exists()) {
            throw BusinessRuleViolation::make(
                'assessments.edit_after_attempts',
                'assessments::errors.edit_after_attempts',
            );
        }

        $this->transaction->run(fn (): bool => (bool) $question->delete());

        $this->events->dispatch(new QuestionRemoved(
            assessmentId: (string) $question->assessment_id,
            organizationId: (string) $assessment?->organization_id,
            questionId: $question->id,
            actorId: $actorId,
        ));
    }
}
