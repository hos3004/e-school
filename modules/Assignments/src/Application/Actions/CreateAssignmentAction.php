<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Events\AssignmentCreated;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء نشاط وإسناده لمقرر (أو مجموعة) بدرجة عليا وموعد تسليم.
 *
 * قواعد العمل:
 *  - الموعد النهائي يجب أن يأتي بعد تاريخ الإسناد.
 *  - نسبة خصم التأخير بين 0 و 100.
 */
final readonly class CreateAssignmentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AssignmentAudienceQueries $audiences,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, string $actorId, string $reason): Assignment
    {
        $organizationId = (string) $data['organization_id'];
        $courseId = (string) $data['course_id'];
        $groupId = isset($data['group_id']) && $data['group_id'] !== '' ? (string) $data['group_id'] : null;
        $staffProfileId = (string) $data['staff_profile_id'];
        $assignedAt = CarbonImmutable::parse((string) Arr::get($data, 'assigned_at', now()->toIso8601String()));
        $dueAt = CarbonImmutable::parse((string) $data['due_at']);
        $allowsLate = (bool) Arr::get($data, 'allows_late', true);
        $latePenalty = (int) Arr::get($data, 'late_penalty_percent', 0);
        $maxScore = (int) $data['max_score'];

        if ($dueAt->lessThanOrEqualTo($assignedAt)) {
            throw BusinessRuleViolation::make(
                'assignments.due_before_assigned',
                'assignments::errors.due_before_assigned',
            );
        }

        if ($latePenalty < 0 || $latePenalty > 100) {
            throw BusinessRuleViolation::make(
                'assignments.invalid_late_penalty',
                'assignments::errors.invalid_late_penalty',
            );
        }

        if ($maxScore < 1) {
            throw BusinessRuleViolation::make(
                'assignments.invalid_max_score',
                'assignments::errors.invalid_max_score',
            );
        }

        if (!$this->audiences->targetBelongsToOrganization($organizationId, $courseId, $groupId)) {
            throw BusinessRuleViolation::make(
                'assignments.invalid_target',
                'assignments::errors.invalid_target',
            );
        }

        if (!$this->audiences->teacherCanTeachTarget(
            $organizationId,
            $staffProfileId,
            $courseId,
            $groupId,
        )) {
            throw BusinessRuleViolation::make(
                'assignments.teacher_not_eligible',
                'assignments::errors.teacher_not_eligible',
            );
        }

        $studentProfileIds = $this->audiences->studentProfileIdsForTarget($organizationId, $courseId, $groupId);

        $assignment = $this->transaction->run(function () use (
            $data,
            $organizationId,
            $courseId,
            $groupId,
            $staffProfileId,
            $assignedAt,
            $dueAt,
            $allowsLate,
            $latePenalty,
            $maxScore,
            $studentProfileIds,
            $actorId,
            $reason,
        ): Assignment {
            $assignment = Assignment::query()->create([
                'organization_id' => $organizationId,
                'course_id' => $courseId,
                'group_id' => $groupId,
                'staff_profile_id' => $staffProfileId,
                'title' => (array) $data['title'],
                'instructions' => (array) ($data['instructions'] ?? []),
                'attachments' => (array) ($data['attachments'] ?? []),
                'assigned_at' => $assignedAt,
                'due_at' => $dueAt,
                'max_score' => $maxScore,
                'allows_late' => $allowsLate,
                'late_penalty_percent' => $latePenalty,
            ]);

            foreach ($studentProfileIds as $studentProfileId) {
                AssignmentSubmission::query()->create([
                    'id' => (string) Str::ulid(),
                    'assignment_id' => (string) $assignment->getKey(),
                    'student_profile_id' => $studentProfileId,
                    'is_late' => false,
                    'status' => AssignmentSubmissionStatus::Pending,
                ]);
            }

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'assignments.created',
                auditableType: 'assignment',
                auditableId: (string) $assignment->getKey(),
                oldValues: null,
                newValues: [
                    'course_id' => $courseId,
                    'group_id' => $groupId,
                    'staff_profile_id' => $staffProfileId,
                    'due_at' => $dueAt->toIso8601String(),
                    'recipient_count' => count($studentProfileIds),
                ],
                reason: $reason,
            );

            return $assignment;
        });

        $this->events->dispatch(new AssignmentCreated(
            assignmentId: (string) $assignment->getKey(),
            organizationId: (string) $assignment->organization_id,
            courseId: (string) $assignment->course_id,
            groupId: $assignment->group_id !== null ? (string) $assignment->group_id : null,
            staffProfileId: (string) $assignment->staff_profile_id,
            maxScore: $assignment->max_score,
            allowsLate: $assignment->allows_late,
            actorId: $actorId,
        ));

        return $assignment;
    }
}
