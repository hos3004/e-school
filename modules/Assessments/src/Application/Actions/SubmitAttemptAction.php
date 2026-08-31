<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\AttemptSubmitted;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسليم محاولة الطالب — بعد التسليم تُقفل الإجابات ولا تُعدَّل.
 */
final readonly class SubmitAttemptAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<int|string, mixed> $answers
     */
    public function execute(AssessmentAttempt $attempt, array $answers, ?string $actorId = null): AssessmentAttempt
    {
        /** @var Assessment $assessment */
        $assessment = $attempt->assessment;

        if ($attempt->submitted_at !== null) {
            throw BusinessRuleViolation::make(
                'assessments.attempt_already_submitted',
                'assessments::errors.attempt_already_submitted',
            );
        }

        $deadline = $this->submissionDeadline($assessment, $attempt);

        if (CarbonImmutable::now('UTC')->gt($deadline)) {
            throw BusinessRuleViolation::make(
                'assessments.submission_deadline_passed',
                'assessments::errors.submission_deadline_passed',
            );
        }

        $submittedAt = CarbonImmutable::now('UTC');

        $questionIds = $assessment->questions()->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $answerIds = array_map('strval', array_keys($answers));

        if (array_diff($questionIds, $answerIds) !== [] || array_diff($answerIds, $questionIds) !== []) {
            throw BusinessRuleViolation::make(
                'assessments.answers_do_not_match_questions',
                'assessments::errors.answers_do_not_match_questions',
            );
        }

        $this->transaction->run(function () use ($attempt, $assessment, $answers, $submittedAt, $actorId): void {
            $attempt->forceFill([
                'answers' => $answers,
                'submitted_at' => $submittedAt,
            ])->save();

            $this->audit->record(
                organizationId: (string) $assessment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.attempt_submitted',
                auditableType: 'assessment_attempt',
                auditableId: (string) $attempt->getKey(),
                oldValues: ['submitted_at' => null],
                newValues: [
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'answer_count' => count($answers),
                ],
                reason: __('assessments::messages.attempt_submitted_reason'),
            );
        });

        $this->events->dispatch(new AttemptSubmitted(
            assessmentId: (string) $attempt->assessment_id,
            organizationId: $assessment->organization_id,
            attemptId: $attempt->id,
            studentProfileId: $attempt->student_profile_id,
            attemptNumber: $attempt->attempt_number,
            actorId: $actorId,
        ));

        return $attempt->refresh();
    }

    /**
     * الموعد النهائي للتسليم: نهاية مدة الاختبار إن حُددت، وإلا نافذة التوفر،
     * مع مهلة سماح بالدقائق من الإعدادات لا من الكود.
     */
    private function submissionDeadline(Assessment $assessment, AssessmentAttempt $attempt): CarbonImmutable
    {
        $graceMinutes = (int) config('assessments.submission.grace_minutes', 0);

        $base = $assessment->duration_minutes !== null
            ? $attempt->started_at->addMinutes($assessment->duration_minutes)
            : $assessment->available_to;

        return $base->addMinutes($graceMinutes);
    }
}
