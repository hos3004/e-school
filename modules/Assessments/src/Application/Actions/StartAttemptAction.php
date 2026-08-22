<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Application\Concerns\ValidatesAssessmentRules;
use Modules\Assessments\Domain\Events\AttemptStarted;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
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
    ) {}

    public function execute(Assessment $assessment, string $studentProfileId, ?string $actorId = null): AssessmentAttempt
    {
        $this->guardWithinAvailabilityWindow($assessment);

        $usedAttempts = $assessment->attempts()
            ->where('student_profile_id', $studentProfileId)
            ->count();

        if ($usedAttempts >= $assessment->max_attempts) {
            throw BusinessRuleViolation::make(
                'assessments.max_attempts_exhausted',
                'assessments::errors.max_attempts_exhausted',
                ['max_attempts' => $assessment->max_attempts],
            );
        }

        $startedAt = CarbonImmutable::now('UTC');

        /** @var AssessmentAttempt $attempt */
        $attempt = $this->transaction->run(fn (): AssessmentAttempt => $assessment->attempts()->create([
            'student_profile_id' => $studentProfileId,
            'attempt_number' => $usedAttempts + 1,
            'started_at' => $startedAt,
            'answers' => [],
            'created_at' => $startedAt,
        ]));

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
