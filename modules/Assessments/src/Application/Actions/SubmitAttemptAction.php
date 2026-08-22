<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\AttemptSubmitted;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
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

        $this->transaction->run(function () use ($attempt, $answers, $submittedAt): void {
            $attempt->forceFill([
                'answers' => $answers,
                'submitted_at' => $submittedAt,
            ])->save();
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
