<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assessments\Application\Concerns\ValidatesAssessmentRules;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Events\AssessmentUpdated;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AcademicCatalogQueries $catalog,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(Assessment $assessment, array $data, string $actorId, string $reason): Assessment
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

        $courseId = array_key_exists('course_id', $data)
            ? ($data['course_id'] === null || $data['course_id'] === '' ? null : (string) $data['course_id'])
            : ($assessment->course_id === null ? null : (string) $assessment->course_id);
        $type = $merged['type'] instanceof AssessmentType
            ? $merged['type']
            : AssessmentType::from((string) $merged['type']);

        if (in_array($type, [AssessmentType::Quiz, AssessmentType::Exam], true) && $courseId === null) {
            throw BusinessRuleViolation::make('assessments.course_required', 'assessments::errors.course_required');
        }

        if ($courseId !== null && !isset($this->catalog->coursesByIds(
            (string) $assessment->organization_id,
            [$courseId],
        )[$courseId])) {
            throw BusinessRuleViolation::make('assessments.invalid_course', 'assessments::errors.invalid_course');
        }

        $lockedFields = [
            'course_id', 'type', 'total_score', 'passing_score', 'duration_minutes',
            'max_attempts', 'available_from',
        ];

        if ($assessment->attempts()->exists() && array_intersect(array_keys($data), $lockedFields) !== []) {
            throw BusinessRuleViolation::make(
                'assessments.settings_locked_after_attempts',
                'assessments::errors.settings_locked_after_attempts',
            );
        }

        $oldValues = $assessment->only([
            'course_id', 'type', 'title', 'instructions', 'total_score', 'passing_score',
            'duration_minutes', 'max_attempts', 'available_from', 'available_to',
        ]);

        $this->transaction->run(function () use ($assessment, $data, $actorId, $reason, $oldValues): void {
            if (\array_key_exists('type', $data) && !$data['type'] instanceof AssessmentType) {
                $data['type'] = AssessmentType::from((string) $data['type']);
            }

            $assessment->fill($data)->save();

            $this->audit->record(
                organizationId: (string) $assessment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.updated',
                auditableType: 'assessment',
                auditableId: (string) $assessment->getKey(),
                oldValues: $oldValues,
                newValues: $assessment->only(array_keys($oldValues)),
                reason: $reason,
            );
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
