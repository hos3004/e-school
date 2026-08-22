<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Events\SubmissionGraded;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
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
    ) {}

    /**
     * @param array<string, mixed> $data score إلزامي، feedback اختياري
     */
    public function execute(AssignmentSubmission $submission, array $data): AssignmentSubmission
    {
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
        $score = (int) $data['score'];

        if ($score < 0 || $score > $assignment->max_score) {
            throw BusinessRuleViolation::make(
                'assignments.score_out_of_range',
                'assignments::errors.score_out_of_range',
                ['max' => $assignment->max_score],
            );
        }

        $this->transaction->run(function () use ($submission, $data, $score): void {
            $submission->forceFill([
                'score' => $score,
                'feedback' => isset($data['feedback']) ? (string) $data['feedback'] : null,
                'graded_by' => auth()->id(),
                'graded_at' => CarbonImmutable::now('UTC'),
                'status' => AssignmentSubmissionStatus::Graded->value,
            ])->save();
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
