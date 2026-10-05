<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Events\SubmissionGraded;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * رصد درجة التسليم — فعل المعلم الذي يغلق دورة حياة التسليم.
 *
 * قواعد العمل:
 *  - لا رصد قبل تسليم الطالب.
 *  - الدرجة بين 0 و max_score المعرَّف على النشاط.
 *  - التسليم المرصود لا يُعاد رصده (أي تصحيح لاحق مسار منفصل موثّق).
 */
final readonly class GradeSubmissionAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param array<string, mixed> $data score إلزامي، feedback اختياري
     */
    public function execute(
        AssignmentSubmission $submission,
        array $data,
        string $actorId,
        string $reason,
    ): AssignmentSubmission {
        $status = $submission->status;

        if (!$status->hasContent()) {
            throw BusinessRuleViolation::make(
                'assignments.grade_before_submission',
                'assignments::errors.grade_before_submission',
            );
        }

        if ($status->isTerminal()) {
            throw BusinessRuleViolation::make(
                'assignments.submission_already_graded',
                'assignments::errors.submission_already_graded',
            );
        }

        if (!$status->canTransitionTo(AssignmentSubmissionStatus::Graded)) {
            throw BusinessRuleViolation::make(
                'assignments.invalid_status_transition',
                'assignments::errors.invalid_status_transition',
                ['from' => $status->label()],
            );
        }

        $assignment = $submission->assignment()->firstOrFail();
        $rawScore = (int) $data['score'];

        if ($rawScore < 0 || $rawScore > $assignment->max_score) {
            throw BusinessRuleViolation::make(
                'assignments.score_out_of_range',
                'assignments::errors.score_out_of_range',
                ['max' => $assignment->max_score],
            );
        }

        $penaltyPoints = $submission->is_late
            ? (int) floor($rawScore * ((int) $assignment->late_penalty_percent / 100))
            : 0;
        $score = max(0, $rawScore - $penaltyPoints);

        $this->transaction->run(function () use (
            $submission,
            $assignment,
            $data,
            $rawScore,
            $penaltyPoints,
            $score,
            $actorId,
            $reason,
        ): void {
            $before = $submission->only([
                'status', 'raw_score', 'penalty_points', 'score', 'feedback', 'graded_by', 'graded_at',
            ]);

            $submission->forceFill([
                'raw_score' => $rawScore,
                'penalty_points' => $penaltyPoints,
                'score' => $score,
                'feedback' => isset($data['feedback']) ? (string) $data['feedback'] : null,
                'graded_by' => $actorId,
                'graded_at' => CarbonImmutable::now('UTC'),
                'status' => AssignmentSubmissionStatus::Graded->value,
            ])->save();

            $this->audit->record(
                organizationId: (string) $assignment->organization_id,
                actorId: $actorId,
                actorType: 'user',
                action: 'assignments.graded',
                auditableType: 'assignment_submission',
                auditableId: (string) $submission->getKey(),
                oldValues: $before,
                newValues: $submission->only([
                    'status', 'raw_score', 'penalty_points', 'score', 'feedback', 'graded_by', 'graded_at',
                ]),
                reason: $reason,
            );
        });

        $this->events->dispatch(new SubmissionGraded(
            submissionId: (string) $submission->getKey(),
            assignmentId: (string) $submission->assignment_id,
            organizationId: (string) $assignment->organization_id,
            studentProfileId: (string) $submission->student_profile_id,
            score: $score,
            maxScore: $assignment->max_score,
            isLate: (bool) $submission->is_late,
        ));

        return $submission->refresh();
    }
}
