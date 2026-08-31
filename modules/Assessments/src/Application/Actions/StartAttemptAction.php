<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assessments\Application\Concerns\ValidatesAssessmentRules;
use Modules\Assessments\Domain\Events\AttemptStarted;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * بدء محاولة اختبار لطالب — يفرض نافذة التوفر وسقف المحاولات.
 */
final readonly class StartAttemptAction
{
    use ValidatesAssessmentRules;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AcademicCatalogQueries $catalog,
        private EnrollmentAdministrationQueries $enrollments,
        private AuditRecorder $audit,
    ) {}

    public function execute(Assessment $assessment, string $studentProfileId, ?string $actorId = null): AssessmentAttempt
    {
        if (!$assessment->hasCompleteQuestionBank()) {
            throw BusinessRuleViolation::make(
                'assessments.question_bank_incomplete',
                'assessments::errors.question_bank_incomplete',
            );
        }

        if ($assessment->course_id !== null) {
            $course = $this->catalog->coursesByIds(
                (string) $assessment->organization_id,
                [(string) $assessment->course_id],
            )[(string) $assessment->course_id] ?? null;

            if ($course === null || $course->programId === null || !isset($this->enrollments->schedulableEnrollmentIdsByStudent(
                (string) $assessment->organization_id,
                $course->programId,
                [$studentProfileId],
            )[$studentProfileId])) {
                throw BusinessRuleViolation::make(
                    'assessments.student_not_eligible',
                    'assessments::errors.student_not_eligible',
                );
            }
        }

        $startedAt = CarbonImmutable::now('UTC');

        /** @var AssessmentAttempt $attempt */
        $attempt = $this->transaction->run(function () use (
            $assessment,
            $studentProfileId,
            $actorId,
            $startedAt,
        ): AssessmentAttempt {
            $lockedAssessment = Assessment::query()->whereKey($assessment->getKey())->lockForUpdate()->firstOrFail();
            $this->guardWithinAvailabilityWindow($lockedAssessment);

            $studentAttempts = $lockedAssessment->attempts()->where('student_profile_id', $studentProfileId);

            if ((clone $studentAttempts)->whereNull('submitted_at')->exists()) {
                throw BusinessRuleViolation::make(
                    'assessments.attempt_in_progress',
                    'assessments::errors.attempt_in_progress',
                );
            }

            $usedAttempts = (clone $studentAttempts)->count();

            if ($usedAttempts >= $lockedAssessment->max_attempts) {
                throw BusinessRuleViolation::make(
                    'assessments.max_attempts_exhausted',
                    'assessments::errors.max_attempts_exhausted',
                    ['max_attempts' => $lockedAssessment->max_attempts],
                );
            }

            $attempt = $lockedAssessment->attempts()->create([
                'student_profile_id' => $studentProfileId,
                'attempt_number' => $usedAttempts + 1,
                'started_at' => $startedAt,
                'answers' => [],
                'created_at' => $startedAt,
            ]);

            $this->audit->record(
                organizationId: (string) $lockedAssessment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.attempt_started',
                auditableType: 'assessment_attempt',
                auditableId: (string) $attempt->getKey(),
                oldValues: null,
                newValues: [
                    'assessment_id' => (string) $lockedAssessment->getKey(),
                    'student_profile_id' => $studentProfileId,
                    'attempt_number' => $attempt->attempt_number,
                ],
                reason: __('assessments::messages.attempt_started_reason'),
            );

            return $attempt;
        });

        $this->events->dispatch(new AttemptStarted(
            assessmentId: $assessment->id,
            organizationId: $assessment->organization_id,
            attemptId: $attempt->id,
            studentProfileId: $attempt->student_profile_id,
            attemptNumber: $attempt->attempt_number,
            durationMinutes: $assessment->duration_minutes,
            actorId: $actorId,
        ));

        return $attempt;
    }
}
