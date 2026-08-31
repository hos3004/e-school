<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assessments\Application\Concerns\ValidatesAssessmentRules;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Events\AssessmentCreated;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
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
        private AcademicCatalogQueries $catalog,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, string $actorId, string $reason, bool $canManageAll = false): Assessment
    {
        $type = $data['type'] instanceof AssessmentType
            ? $data['type']
            : AssessmentType::from((string) $data['type']);

        $totalScore = (int) $data['total_score'];
        $passingScore = (int) $data['passing_score'];
        $maxAttempts = (int) $data['max_attempts'];
        $organizationId = (string) $data['organization_id'];
        $courseId = isset($data['course_id']) && $data['course_id'] !== '' ? (string) $data['course_id'] : null;

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

        if (in_array($type, [AssessmentType::Quiz, AssessmentType::Exam], true) && $courseId === null) {
            throw BusinessRuleViolation::make(
                'assessments.course_required',
                'assessments::errors.course_required',
            );
        }

        if ($courseId !== null && !isset($this->catalog->coursesByIds($organizationId, [$courseId])[$courseId])) {
            throw BusinessRuleViolation::make(
                'assessments.invalid_course',
                'assessments::errors.invalid_course',
            );
        }

        if (!$canManageAll) {
            $staff = $this->staff->findActiveProfileForUser($actorId);

            if ($courseId === null || $staff === null || !$this->qualifications->isQualified($staff['id'], $courseId)) {
                throw BusinessRuleViolation::make(
                    'assessments.course_not_authorized',
                    'assessments::errors.course_not_authorized',
                );
            }
        }

        /** @var Assessment $assessment */
        $assessment = $this->transaction->run(function () use (
            $data,
            $type,
            $organizationId,
            $courseId,
            $actorId,
            $reason,
        ): Assessment {
            $assessment = Assessment::query()->create([
                ...$data,
                'organization_id' => $organizationId,
                'course_id' => $courseId,
                'type' => $type,
                'instructions' => $data['instructions'] ?? [],
                'created_by' => $actorId,
            ]);

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'assessments.created',
                auditableType: 'assessment',
                auditableId: (string) $assessment->getKey(),
                oldValues: null,
                newValues: $assessment->only([
                    'course_id', 'type', 'total_score', 'passing_score', 'duration_minutes',
                    'max_attempts', 'available_from', 'available_to',
                ]),
                reason: $reason,
            );

            return $assessment;
        });

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
