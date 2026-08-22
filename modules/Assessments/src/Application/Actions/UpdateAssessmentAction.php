<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assessments\Application\Concerns\ValidatesAssessmentRules;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Events\AssessmentUpdated;
use Modules\Assessments\Domain\Models\Assessment;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعديل بيانات اختبار قائم — يُسجَّل الحدث بأسماء الحقول المتغيرة للتدقيق.
 */
final readonly class UpdateAssessmentAction
{
    use ValidatesAssessmentRules;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(Assessment $assessment, array $data, ?string $actorId = null): Assessment
    {
        $merged = [
            'type' => $data['type'] ?? $assessment->type,
            'total_score' => $data['total_score'] ?? $assessment->total_score,
            'passing_score' => $data['passing_score'] ?? $assessment->passing_score,
            'max_attempts' => $data['max_attempts'] ?? $assessment->max_attempts,
            'available_from' => $data['available_from'] ?? $assessment->available_from,
            'available_to' => $data['available_to'] ?? $assessment->available_to,
        ];

        $this->guardScoreConsistency(
            (int) $merged['total_score'],
            (int) $merged['passing_score'],
        );
        $this->guardAvailabilityWindowValues($merged['available_from'], $merged['available_to']);

        if ((int) $merged['max_attempts'] < 1) {
            throw BusinessRuleViolation::make(
                'assessments.invalid_max_attempts',
                'assessments::errors.invalid_max_attempts',
            );
        }

        $this->transaction->run(function () use ($assessment, $data): void {
            if (\array_key_exists('type', $data) && !$data['type'] instanceof AssessmentType) {
                $data['type'] = AssessmentType::from((string) $data['type']);
            }

            $assessment->fill($data)->save();
        });

        $changed = array_values(array_intersect(
            array_keys($data),
            array_keys($assessment->getChanges()),
        ));

        $this->events->dispatch(new AssessmentUpdated(
            assessmentId: $assessment->id,
            organizationId: $assessment->organization_id,
            changedFields: $changed,
            actorId: $actorId,
        ));

        return $assessment->refresh();
    }
}
