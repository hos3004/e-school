<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Events\AssignmentSubmitted;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسليم الطالب لنشاطه.
 *
 * قواعد العمل:
 *  - لا تسليم بعد الموعد إلا إذا سمح النشاط بالتأخير.
 *  - لا إعادة تسليم بعد الرصد (graded حالة نهائية).
 *  - التأخير يُحتسب آليًا من due_at ويُخزَّن على الصف — لا يقرره المستخدم.
 */
final readonly class SubmitAssignmentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data  content و attachments اختيارية
     */
    public function execute(AssignmentSubmission $submission, array $data): AssignmentSubmission
    {
        $status = $submission->status;

        if (! $status->canTransitionTo(AssignmentSubmissionStatus::Submitted)
            && ! $status->canTransitionTo(AssignmentSubmissionStatus::Late)) {
            throw BusinessRuleViolation::make(
                'assignments.submission_not_pending',
                'assignments::errors.submission_not_pending',
                ['status' => $status->label()],
            );
        }

        $assignment = $submission->assignment()->firstOrFail();
        $now = CarbonImmutable::now('UTC');
        $isLate = $assignment->isPastDue();

        if ($isLate && ! $assignment->allows_late) {
            throw BusinessRuleViolation::make(
                'assignments.late_not_allowed',
                'assignments::errors.late_not_allowed',
                ['due_at' => $assignment->due_at->toIso8601String()],
            );
        }

        $this->transaction->run(function () use ($submission, $data, $now, $isLate): void {
            $target = $isLate ? AssignmentSubmissionStatus::Late : AssignmentSubmissionStatus::Submitted;

            $submission->forceFill([
                'submitted_at' => $now,
                'is_late' => $isLate,
                'content' => isset($data['content']) ? (string) $data['content'] : null,
                'attachments' => (array) ($data['attachments'] ?? []),
                'status' => $target->value,
            ])->save();
        });

        $this->events->dispatch(new AssignmentSubmitted(
            submissionId: (string) $submission->getKey(),
            assignmentId: (string) $submission->assignment_id,
            organizationId: (string) $assignment->organization_id,
            studentProfileId: (string) $submission->student_profile_id,
            isLate: $isLate,
        ));

        return $submission->refresh();
    }
}
