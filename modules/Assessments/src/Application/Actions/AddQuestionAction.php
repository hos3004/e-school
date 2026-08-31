<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Enums\QuestionType;
use Modules\Assessments\Domain\Events\QuestionAdded;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\Question;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(Assessment $assessment, array $data, string $actorId, string $reason): Question
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

        if ($assessment->attempts()->exists()) {
            throw BusinessRuleViolation::make(
                'assessments.edit_after_attempts',
                'assessments::errors.edit_after_attempts',
            );
        }

        $options = isset($data['options']) ? array_values((array) $data['options']) : null;
        $correctAnswer = isset($data['correct_answer']) ? (array) $data['correct_answer'] : null;

        if ($type === QuestionType::Mcq) {
            $keys = array_values(array_filter(array_map(
                static fn (mixed $option): string => is_array($option) ? (string) ($option['key'] ?? '') : '',
                $options ?? [],
            )));

            if (count($keys) < 2 || count($keys) !== count(array_unique($keys))
                || !in_array((string) ($correctAnswer['key'] ?? ''), $keys, true)) {
                throw BusinessRuleViolation::make(
                    'assessments.invalid_mcq_options',
                    'assessments::errors.invalid_mcq_options',
                );
            }
        } elseif ($type === QuestionType::TrueFalse) {
            $options = [
                ['key' => 'true', 'text' => ['ar' => 'صحيح', 'en' => 'True', 'fr' => 'Vrai']],
                ['key' => 'false', 'text' => ['ar' => 'خطأ', 'en' => 'False', 'fr' => 'Faux']],
            ];

            if (!in_array((string) ($correctAnswer['key'] ?? ''), ['true', 'false'], true)) {
                throw BusinessRuleViolation::make(
                    'assessments.invalid_true_false_answer',
                    'assessments::errors.invalid_true_false_answer',
                );
            }
        } elseif ($type === QuestionType::Essay) {
            $options = null;
            $correctAnswer = null;
        } else {
            $options = null;
        }

        /** @var Question $question */
        $question = $this->transaction->run(function () use (
            $assessment,
            $data,
            $type,
            $score,
            $sortOrder,
            $options,
            $correctAnswer,
            $actorId,
            $reason,
        ): Question {
            $question = $assessment->questions()->create([
                ...$data,
                'type' => $type,
                'options' => $options,
                'correct_answer' => $correctAnswer,
                'score' => $score,
                'sort_order' => $sortOrder,
                'created_at' => now('UTC'),
            ]);

            $this->audit->record(
                organizationId: (string) $assessment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.question_added',
                auditableType: 'assessment',
                auditableId: (string) $assessment->getKey(),
                oldValues: null,
                newValues: [
                    'question_id' => (string) $question->getKey(),
                    'type' => $question->type->value,
                    'score' => $question->score,
                    'sort_order' => $question->sort_order,
                ],
                reason: $reason,
            );

            return $question;
        });

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
