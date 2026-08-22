<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Application\Concerns\ValidatesAssessmentRules;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Events\AssessmentCreated;
use Modules\Assessments\Domain\Models\Assessment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء اختبار جديد — مسودة تُفتح نافذة توفرها في موعدها.
 */
final readonly class CreateAssessmentAction
{
    use ValidatesAssessmentRules;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, ?string $actorId = null): Assessment
    {
        $type = $data['type'] instanceof AssessmentType
            ? $data['type']
            : AssessmentType::from((string) $data['type']);

        $totalScore = (int) $data['total_score'];
        $passingScore = (int) $data['passing_score'];
        $maxAttempts = (int) $data['max_attempts'];

        $this->guardScoreConsistency($totalScore, $passingScore);
        $this->guardAvailabilityWindowValues(
            availableFrom: $data['available_from'],
            availableTo: $data['available_to'],
        );

        if ($maxAttempts < 1) {
            throw BusinessRuleViolation::make(
                'assessments.invalid_max_attempts',
                'assessments::errors.invalid_max_attempts',
            );
        }

        /** @var Assessment $assessment */
        $assessment = $this->transaction->run(fn (): Assessment => Assessment::create([
            ...$data,
            'type' => $type,
            'created_by' => (string) ($actorId ?? auth()->id()),
        ]));

        $this->events->dispatch(new AssessmentCreated(
            assessmentId: $assessment->id,
            organizationId: $assessment->organization_id,
            courseId: $assessment->course_id,
            type: $assessment->type->value,
            totalScore: $assessment->total_score,
            passingScore: $assessment->passing_score,
            maxAttempts: $assessment->max_attempts,
            createdBy: $assessment->created_by,
        ));

        return $assessment;
    }
}
