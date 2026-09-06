<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Contracts\StudentAssignmentResultQueries;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Assignments\Domain\ValueObjects\StudentAssignmentResult;

final readonly class StudentAssignmentResultQueryService implements StudentAssignmentResultQueries
{
    public function __construct(private AssignmentAudienceQueries $audiences) {}

    public function forStudent(string $organizationId, string $studentUserId, ?string $staffProfileId = null): array
    {
        $audience = $this->audiences->forUser($organizationId, $studentUserId);
        if ($audience->studentProfileId === null
            || ($staffProfileId !== null && !$this->audiences->staffProfileBelongsToOrganization($organizationId, $staffProfileId))) {
            return [];
        }
        $rows = Assignment::query()->forOrganization($organizationId)->where('assigned_at', '<=', now('UTC'))
            ->when($staffProfileId !== null, static fn (Builder $query): Builder => $query->where('staff_profile_id', $staffProfileId))
            ->where(static function (Builder $query) use ($audience): void {
                $query->where(static fn (Builder $courses): Builder => $courses->whereNull('group_id')->whereIn('course_id', $audience->activeCourseIds))
                    ->orWhereIn('group_id', $audience->activeGroupIds);
            })->with(['submissions' => static fn ($query) => $query
            ->where('student_profile_id', $audience->studentProfileId)])
            ->orderByDesc('assigned_at')->orderBy('id')->get();
        $results = [];
        foreach ($rows as $assignment) {
            if (!$audience->targetsStudent($assignment->course_id, $assignment->group_id)
                || !$this->audiences->targetBelongsToOrganization($organizationId, $assignment->course_id, $assignment->group_id)) {
                continue;
            }
            /** @var AssignmentSubmission|null $submission */
            $submission = $assignment->submissions->first();
            $state = $submission?->status;
            $hasContent = $state?->hasContent() ?? false;
            $graded = $state === AssignmentSubmissionStatus::Graded;
            $fallback = $assignment->isPastDue() ? 'overdue' : 'open';
            $results[] = new StudentAssignmentResult(
                id: (string) $assignment->getKey(), courseId: $assignment->course_id, title: $assignment->title,
                dueAt: $assignment->due_at->toIso8601String(),
                submissionStatus: $state->value ?? $fallback,
                submissionStatusLabel: $state?->label() ?? __('portal.statuses.'.$fallback),
                submittedAt: $hasContent ? $submission?->submitted_at?->toIso8601String() : null,
                submissionContent: $hasContent ? $submission?->content : null,
                gradedAt: $graded ? $submission?->graded_at?->toIso8601String() : null,
                score: $graded ? $submission?->score : null, maxScore: $assignment->max_score,
                feedback: $graded ? $submission?->feedback : null,
            );
        }

        return $results;
    }
}
