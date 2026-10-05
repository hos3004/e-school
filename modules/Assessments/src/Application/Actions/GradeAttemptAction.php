<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Domain\Events\AttemptGraded;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تصحيح محاولة وإعلان النتيجة — قيد نهائي لا يُعدَّل بعد اعتماده.
 *
 * النجاح يُحتسب بمقارنة الدرجة بعلامة النجاح المخزنة على الاختبار
 * وقت التصحيح، وليس بقيمة محسوبة لاحقًا.
 */
final readonly class GradeAttemptAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        AssessmentAttempt $attempt,
        int $score,
        string $actorId,
        string $reason,
        ?string $feedback = null,
    ): AssessmentAttempt {
        /** @var Assessment $assessment */
        $assessment = $attempt->assessment;

        if ($attempt->submitted_at === null) {
            throw BusinessRuleViolation::make(
                'assessments.grade_before_submission',
                'assessments::errors.grade_before_submission',
            );
        }

        if ($attempt->graded_at !== null) {
            throw BusinessRuleViolation::make(
                'assessments.attempt_already_graded',
                'assessments::errors.attempt_already_graded',
            );
        }

        if ($score < 0 || $score > $assessment->total_score) {
            throw BusinessRuleViolation::make(
                'assessments.score_out_of_range',
                'assessments::errors.score_out_of_range',
                ['total_score' => $assessment->total_score],
            );
        }

        $passed = $score >= $assessment->passing_score;
        $gradedAt = CarbonImmutable::now('UTC');
        $graderId = $actorId;

        $this->transaction->run(function () use (
            $attempt,
            $assessment,
            $score,
            $passed,
            $gradedAt,
            $graderId,
            $reason,
            $feedback,
        ): void {
            $attempt->forceFill([
                'score' => $score,
                'passed' => $passed,
                'graded_by' => $graderId,
                'graded_at' => $gradedAt,
                'feedback' => $feedback,
            ])->save();

            $this->audit->record(
                organizationId: (string) $assessment->organization_id,
                actorId: $graderId,
                actorType: 'user',
                action: 'assessments.attempt_graded',
                auditableType: 'assessment_attempt',
                auditableId: (string) $attempt->getKey(),
                oldValues: ['score' => null, 'passed' => null, 'graded_at' => null],
                newValues: [
                    'score' => $score,
                    'passed' => $passed,
                    'graded_at' => $gradedAt->toIso8601String(),
                    'feedback' => $feedback,
                ],
                reason: $reason,
            );
        });

        $this->events->dispatch(new AttemptGraded(
            assessmentId: (string) $attempt->assessment_id,
            organizationId: $assessment->organization_id,
            attemptId: $attempt->id,
            studentProfileId: $attempt->student_profile_id,
            attemptNumber: $attempt->attempt_number,
            score: $score,
            passed: $passed,
            reactivationRequestId: $attempt->reactivation_request_id,
            actorId: $graderId,
        ));

        return $attempt->refresh();
    }
}
