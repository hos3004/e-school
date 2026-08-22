<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Modules\Assignments\Domain\Events\AssignmentCreated;
use Modules\Assignments\Domain\Models\Assignment;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Assignment
    {
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

        $assignment = $this->transaction->run(function () use ($data, $assignedAt, $dueAt, $allowsLate, $latePenalty, $maxScore): Assignment {
            return Assignment::query()->create([
                'organization_id' => (string) $data['organization_id'],
                'course_id' => (string) $data['course_id'],
                'group_id' => isset($data['group_id']) ? (string) $data['group_id'] : null,
                'staff_profile_id' => (string) $data['staff_profile_id'],
                'title' => (array) $data['title'],
                'instructions' => (array) ($data['instructions'] ?? []),
                'attachments' => (array) ($data['attachments'] ?? []),
                'assigned_at' => $assignedAt,
                'due_at' => $dueAt,
                'max_score' => $maxScore,
                'allows_late' => $allowsLate,
                'late_penalty_percent' => $latePenalty,
            ]);
        });

        $this->events->dispatch(new AssignmentCreated(
            assignmentId: (string) $assignment->getKey(),
            organizationId: (string) $assignment->organization_id,
            courseId: (string) $assignment->course_id,
            groupId: $assignment->group_id !== null ? (string) $assignment->group_id : null,
            staffProfileId: (string) $assignment->staff_profile_id,
            maxScore: $assignment->max_score,
            allowsLate: $assignment->allows_late,
        ));

        return $assignment;
    }
}
