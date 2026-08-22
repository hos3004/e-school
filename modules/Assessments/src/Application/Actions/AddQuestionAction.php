<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Enums\QuestionType;
use Modules\Assessments\Domain\Events\QuestionAdded;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\Question;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إضافة سؤال إلى اختبار — مع حراس مجموع الدرجات وترتيب السؤال.
 */
final readonly class AddQuestionAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(Assessment $assessment, array $data, ?string $actorId = null): Question
    {
        $type = $data['type'] instanceof QuestionType
            ? $data['type']
            : QuestionType::from((string) $data['type']);

        $score = (int) $data['score'];

        if ($score < 1) {
            throw BusinessRuleViolation::make(
                'assessments.question_score_invalid',
                'assessments::errors.question_score_invalid',
            );
        }

        if ($assessment->questions()->sum('score') + $score > $assessment->total_score) {
            throw BusinessRuleViolation::make(
                'assessments.questions_score_exceeds_total',
                'assessments::errors.questions_score_exceeds_total',
            );
        }

        $sortOrder = (int) ($data['sort_order'] ?? ($assessment->questions()->count() + 1));

        if ($assessment->questions()->where('sort_order', $sortOrder)->exists()) {
            throw BusinessRuleViolation::make(
                'assessments.question_sort_order_taken',
                'assessments::errors.question_sort_order_taken',
                ['sort_order' => $sortOrder],
            );
        }

        /** @var Question $question */
        $question = $this->transaction->run(fn (): Question => $assessment->questions()->create([
            ...$data,
            'type' => $type,
            'score' => $score,
            'sort_order' => $sortOrder,
        ]));

        $this->events->dispatch(new QuestionAdded(
            assessmentId: $assessment->id,
            organizationId: $assessment->organization_id,
            questionId: $question->id,
            type: $question->type->value,
            score: $question->score,
            sortOrder: $question->sort_order,
            actorId: $actorId,
        ));

        return $question;
    }
}
