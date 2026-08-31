<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class UpdateAssignmentAction
{
    public function __construct(
        private Transaction $transaction,
        private AssignmentAudienceQueries $audiences,
        private AuditRecorder $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(Assignment $assignment, array $data, string $actorId, string $reason): Assignment
    {
        $organizationId = (string) $assignment->organization_id;
        $courseId = (string) ($data['course_id'] ?? $assignment->course_id);
        $groupId = array_key_exists('group_id', $data)
            ? ($data['group_id'] === null || $data['group_id'] === '' ? null : (string) $data['group_id'])
            : ($assignment->group_id === null ? null : (string) $assignment->group_id);
        $staffProfileId = (string) ($data['staff_profile_id'] ?? $assignment->staff_profile_id);
        $assignedAt = CarbonImmutable::parse((string) Arr::get($data, 'assigned_at', $assignment->assigned_at));
        $dueAt = CarbonImmutable::parse((string) Arr::get($data, 'due_at', $assignment->due_at));
        $maxScore = (int) Arr::get($data, 'max_score', $assignment->max_score);
        $latePenalty = (int) Arr::get($data, 'late_penalty_percent', $assignment->late_penalty_percent);

        if ($dueAt->lessThanOrEqualTo($assignedAt) || $maxScore < 1 || $latePenalty < 0 || $latePenalty > 100) {
            throw BusinessRuleViolation::make(
                'assignments.invalid_update',
                'assignments::errors.invalid_update',
            );
        }

        if (!$this->audiences->targetBelongsToOrganization($organizationId, $courseId, $groupId)
            || !$this->audiences->teacherCanTeachTarget($organizationId, $staffProfileId, $courseId, $groupId)) {
            throw BusinessRuleViolation::make(
                'assignments.invalid_target',
                'assignments::errors.invalid_target',
            );
        }

        $audienceChanged = $courseId !== (string) $assignment->course_id
            || $groupId !== ($assignment->group_id === null ? null : (string) $assignment->group_id);

        if ($audienceChanged && $assignment->submissions()->exists()) {
            throw BusinessRuleViolation::make(
                'assignments.audience_locked',
                'assignments::errors.audience_locked',
            );
        }

        $oldValues = $assignment->only([
            'course_id', 'group_id', 'staff_profile_id', 'title', 'instructions',
            'assigned_at', 'due_at', 'max_score', 'allows_late', 'late_penalty_percent',
        ]);

        return $this->transaction->run(function () use (
            $assignment,
            $data,
            $organizationId,
            $courseId,
            $groupId,
            $staffProfileId,
            $assignedAt,
            $dueAt,
            $maxScore,
            $latePenalty,
            $audienceChanged,
            $actorId,
            $reason,
            $oldValues,
        ): Assignment {
            $assignment->forceFill([
                'course_id' => $courseId,
                'group_id' => $groupId,
                'staff_profile_id' => $staffProfileId,
                'title' => (array) ($data['title'] ?? $assignment->title),
                'instructions' => (array) ($data['instructions'] ?? $assignment->instructions),
                'attachments' => (array) ($data['attachments'] ?? $assignment->attachments),
                'assigned_at' => $assignedAt,
                'due_at' => $dueAt,
                'max_score' => $maxScore,
                'allows_late' => (bool) ($data['allows_late'] ?? $assignment->allows_late),
                'late_penalty_percent' => $latePenalty,
            ])->save();

            if ($audienceChanged) {
                foreach ($this->audiences->studentProfileIdsForTarget($organizationId, $courseId, $groupId) as $studentProfileId) {
                    $assignment->submissions()->firstOrCreate(
                        ['student_profile_id' => $studentProfileId],
                        ['is_late' => false, 'status' => AssignmentSubmissionStatus::Pending],
                    );
                }
            }

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'assignments.updated',
                auditableType: 'assignment',
                auditableId: (string) $assignment->getKey(),
                oldValues: $oldValues,
                newValues: $assignment->only(array_keys($oldValues)),
                reason: $reason,
            );

            return $assignment->refresh();
        });
    }
}
