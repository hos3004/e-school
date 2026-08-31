<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\Models\Question;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class AssessmentAdministrationQueryService
{
    public function __construct(
        private AcademicCatalogQueries $catalog,
        private StudentDirectoryQueries $students,
        private UserQueryService $users,
        private AuditQueryService $audit,
    ) {}

    /** @return array<string, string> */
    public function programOptions(string $organizationId): array
    {
        $options = [];

        foreach ($this->catalog->programs($organizationId) as $program) {
            $options[$program->id] = $this->localized($program->name, $program->code);
        }

        return $options;
    }

    /** @return array<string, string> */
    public function courseOptions(string $organizationId, ?string $programId = null): array
    {
        $programIds = $programId === null || $programId === ''
            ? array_keys($this->programOptions($organizationId))
            : [$programId];
        $options = [];

        foreach ($programIds as $id) {
            foreach ($this->catalog->courses($organizationId, $id) as $course) {
                $options[$course->id] = $this->localized($course->name, $course->code);
            }
        }

        asort($options);

        return $options;
    }

    public function programIdForCourse(string $organizationId, ?string $courseId): ?string
    {
        if ($courseId === null || $courseId === '') {
            return null;
        }

        return $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId]->programId ?? null;
    }

    public function courseBelongsToOrganization(string $organizationId, ?string $courseId): bool
    {
        return $courseId === null || $courseId === ''
            || isset($this->catalog->coursesByIds($organizationId, [$courseId])[$courseId]);
    }

    public function courseLabel(string $organizationId, ?string $courseId): string
    {
        if ($courseId === null || $courseId === '') {
            return __('assessments::messages.not_applicable');
        }

        $course = $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        return $course === null
            ? __('assessments::messages.not_available')
            : $this->localized($course->name, $course->code);
    }

    /**
     * @return array{questions: int, allocated_score: int, remaining_score: int, attempts: int, awaiting_grading: int, passed: int, failed: int}
     */
    public function metrics(Assessment $assessment): array
    {
        $allocated = (int) $assessment->questions()->sum('score');

        return [
            'questions' => $assessment->questions()->count(),
            'allocated_score' => $allocated,
            'remaining_score' => max(0, $assessment->total_score - $allocated),
            'attempts' => $assessment->attempts()->count(),
            'awaiting_grading' => $assessment->attempts()->whereNotNull('submitted_at')->whereNull('graded_at')->count(),
            'passed' => $assessment->attempts()->where('passed', true)->count(),
            'failed' => $assessment->attempts()->where('passed', false)->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function questions(Assessment $assessment): array
    {
        return $assessment->questions()->ordered()->get()->map(fn (Question $question): array => [
            'id' => (string) $question->getKey(),
            'order' => $question->sort_order,
            'type' => $question->type->label(),
            'body' => $this->localized($question->body, __('assessments::messages.not_available')),
            'score' => $question->score,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function attempts(Assessment $assessment): array
    {
        $attempts = $assessment->attempts()->orderByDesc('started_at')->get();
        $studentIds = $attempts->pluck('student_profile_id')->map(static fn (mixed $id): string => (string) $id)->all();
        $students = $this->students->byIds((string) $assessment->organization_id, $studentIds);
        $users = $this->users->summariesByIds(array_values(array_map(
            static fn ($student): string => $student->userId,
            $students,
        )));

        return $attempts->map(function (AssessmentAttempt $attempt) use ($students, $users): array {
            $student = $students[(string) $attempt->student_profile_id] ?? null;
            $user = $student === null ? null : ($users[$student->userId] ?? null);

            return [
                'id' => (string) $attempt->getKey(),
                'student' => $user->name ?? $student->studentCode ?? __('assessments::messages.not_available'),
                'student_code' => $student?->studentCode,
                'attempt_number' => $attempt->attempt_number,
                'status' => $this->attemptStatusLabel($attempt),
                'started_at' => $attempt->started_at,
                'submitted_at' => $attempt->submitted_at,
                'score' => $attempt->score,
                'passed' => $attempt->passed,
            ];
        })->all();
    }

    public function studentLabel(string $organizationId, string $studentProfileId): string
    {
        $student = $this->students->find($organizationId, $studentProfileId);
        $user = $student === null ? null : $this->users->findSummary($student->userId);

        return $user->name ?? $student->studentCode ?? __('assessments::messages.not_available');
    }

    /** @return list<array<string, mixed>> */
    public function attemptAnswers(AssessmentAttempt $attempt): array
    {
        return $attempt->assessment->questions()->ordered()->get()->map(function (Question $question) use ($attempt): array {
            $answer = $attempt->answers[(string) $question->getKey()] ?? null;

            return [
                'order' => $question->sort_order,
                'question' => $this->localized($question->body, __('assessments::messages.not_available')),
                'type' => $question->type->label(),
                'answer' => $this->answerLabel($answer),
                'correct_answer' => $this->answerLabel($question->correct_answer),
                'score' => $question->score,
            ];
        })->all();
    }

    public function attemptStatusLabel(AssessmentAttempt $attempt): string
    {
        $status = match (true) {
            $attempt->submitted_at === null => 'in_progress',
            $attempt->graded_at === null => 'awaiting_grading',
            $attempt->passed === true => 'passed',
            default => 'failed',
        };

        return __('assessments::status.attempt.'.$status);
    }

    /** @return list<array<string, mixed>> */
    public function auditTrail(string $organizationId, string $auditableType, string $auditableId): array
    {
        $entries = $this->audit->paginateForOrganization($organizationId, [
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
        ], 50);
        $actors = $this->users->summariesByIds(array_values(array_filter(array_map(
            static fn ($entry): ?string => $entry->actorId,
            $entries->items(),
        ))));

        return array_values(array_map(static function ($entry) use ($actors): array {
            $translation = 'assessments::audit_actions.'.$entry->action;

            return [
                'action' => __($translation) === $translation ? $entry->action : __($translation),
                'actor' => $entry->actorId === null
                    ? __('assessments::messages.system_actor')
                    : ($actors[$entry->actorId]->name ?? $entry->actorId),
                'reason' => $entry->reason,
                'created_at' => $entry->createdAt,
            ];
        }, $entries->items()));
    }

    /** @param array<string, string> $values */
    public function localized(array $values, string $fallback): string
    {
        return (string) ($values[app()->getLocale()] ?? $values['ar'] ?? $values['en'] ?? reset($values) ?: $fallback);
    }

    private function answerLabel(mixed $answer): string
    {
        if ($answer === null || $answer === '') {
            return __('assessments::messages.not_answered');
        }

        if (is_array($answer)) {
            $value = $answer['value'] ?? $answer['key'] ?? null;

            if (is_scalar($value)) {
                return (string) $value;
            }

            return (string) json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return is_scalar($answer) ? (string) $answer : __('assessments::messages.not_available');
    }
}
