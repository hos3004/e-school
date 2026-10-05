<?php

declare(strict_types=1);

namespace Modules\Students\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Students\Application\Actions\RejectRegistrationApplicationAction;
use Modules\Students\Application\Actions\ReviewRegistrationApplicationAction;
use Modules\Students\Application\Actions\SaveRegistrationFormAction;
use Modules\Students\Application\Queries\RegistrationApplicationFilterService;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Shared\Support\Transaction;

/** Console boundary: Students models remain owned here; callers receive arrays and IDs. */
final readonly class ConsoleRegistrationService
{
    public function __construct(
        private SaveRegistrationFormAction $saveForm,
        private RegistrationApplicationFilterService $filters,
        private ReviewRegistrationApplicationAction $review,
        private RejectRegistrationApplicationAction $reject,
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forms(string $organizationId, ?string $courseId = null): array
    {
        Gate::authorize('viewAny', RegistrationForm::class);

        return RegistrationForm::query()->forOrganization($organizationId)
            ->when($courseId !== null, fn (Builder $query) => $query->where('preferred_course_id', $courseId))
            ->with('questions')->withCount('applications')->latest('updated_at')->get()
            ->map(fn (RegistrationForm $form): array => $this->formData($form))->all();
    }

    /** @return array<string, mixed> */
    public function form(string $organizationId, string $id): array
    {
        $form = RegistrationForm::query()->forOrganization($organizationId)->with('questions')->withCount('applications')->findOrFail($id);
        Gate::authorize('view', $form);

        return $this->formData($form);
    }

    /** @param array<string, mixed> $data */
    public function save(string $organizationId, ?string $id, array $data, string $actorId): string
    {
        return $this->saveForm->execute($organizationId, $id, $data, $actorId, __('console_registration.audit.'.($id === null ? 'form_created' : 'form_updated')));
    }

    /** @return array<string, int> */
    public function counts(string $organizationId, ?string $courseId = null): array
    {
        $query = RegistrationApplication::query()->forOrganization($organizationId)
            ->when($courseId !== null, fn (Builder $query) => $query->where('preferred_course_id', $courseId));
        $counts = $query->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'requests' => (int) ($counts[RegistrationStatus::Submitted->value] ?? 0) + (int) ($counts[RegistrationStatus::UnderReview->value] ?? 0),
            'accepted' => (int) ($counts[RegistrationStatus::WaitingAssignment->value] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function applications(string $organizationId, array $filters, string $timezone): array
    {
        $query = RegistrationApplication::query()->forOrganization($organizationId)->with(['registrationForm', 'studentProfile:id,student_code']);
        if (($filters['course'] ?? null) !== null) {
            $query->where('preferred_course_id', $filters['course']);
        }
        if (($filters['form'] ?? null) !== null) {
            $owned = RegistrationForm::query()->forOrganization($organizationId)->findOrFail($filters['form']);
            $query->where('registration_form_id', $owned->id);
        }
        if (($filters['search'] ?? '') !== '') {
            $query->search((string) $filters['search']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        } elseif (($filters['stage'] ?? '') === 'accepted') {
            $query->where('status', RegistrationStatus::WaitingAssignment);
        } else {
            $query->whereIn('status', [RegistrationStatus::Submitted, RegistrationStatus::UnderReview]);
        }
        if (($filters['country'] ?? '') !== '') {
            $query->where('country_id', $filters['country']);
        }
        $this->filters->applyAgeRange($query, isset($filters['age_from']) ? (int) $filters['age_from'] : null, isset($filters['age_to']) ? (int) $filters['age_to'] : null, $timezone);
        $this->filters->applySubmissionDateRange($query, $filters['date_from'] ?? null, $filters['date_to'] ?? null, $timezone);
        $filterable = $this->filters->filterableQuestions($organizationId);
        foreach ($filterable as $question) {
            if ($question->id !== ($filters['question'] ?? null)) {
                continue;
            }
            if ($question->type->value === 'number') {
                $this->filters->applyNumberAnswerRange($query, $question->id, isset($filters['answer_from']) ? (float) $filters['answer_from'] : null, isset($filters['answer_to']) ? (float) $filters['answer_to'] : null);
            } else {
                $this->filters->applySelectAnswer($query, $question->id, isset($filters['answer']) ? [(string) $filters['answer']] : []);
            }
        }
        $page = $query->latest('created_at')->paginate((int) config('console.directory_per_page'))->withQueryString();

        return [
            'data' => $page->getCollection()->map(fn (RegistrationApplication $application): array => $this->applicationData($application, $timezone))->all(),
            'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'next_page_url' => $page->nextPageUrl(), 'prev_page_url' => $page->previousPageUrl(),
        ];
    }

    /** @return array<string, mixed> */
    public function application(string $organizationId, string $id, string $timezone): array
    {
        $application = RegistrationApplication::query()->forOrganization($organizationId)->with(['registrationForm', 'studentProfile:id,student_code'])->findOrFail($id);
        Gate::authorize('view', $application);

        return $this->applicationData($application, $timezone);
    }

    public function review(string $organizationId, string $id, string $actorId): void
    {
        $this->recordDecision($organizationId, $id, $actorId, 'review', __('console_registration.audit.review'));
    }

    public function reject(string $organizationId, string $id, string $actorId, string $reason): void
    {
        $this->recordDecision($organizationId, $id, $actorId, 'reject', $reason);
    }

    private function recordDecision(string $organizationId, string $id, string $actorId, string $decision, string $reason): void
    {
        $this->transaction->run(function () use ($organizationId, $id, $actorId, $decision, $reason): void {
            $application = RegistrationApplication::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($id);
            Gate::authorize($decision, $application);
            $before = $application->status->value;
            $saved = $decision === 'review'
                ? $this->review->execute($application, $actorId)
                : $this->reject->execute($application, $reason, $actorId);
            $this->audit->record(
                organizationId: $organizationId, actorId: $actorId, actorType: 'user',
                action: $decision === 'review' ? 'academic_status.registration_reviewed' : 'academic_status.registration_rejected',
                auditableType: 'registration_application', auditableId: $id,
                oldValues: ['status' => $before], newValues: ['status' => $saved->status->value],
                reason: $reason,
            );
        });
    }

    /** @return list<array<string, mixed>> */
    public function questionsForFiltering(string $organizationId): array
    {
        return array_map(static fn ($question): array => [
            'id' => $question->id, 'label' => $question->label, 'type' => $question->type->value, 'options' => $question->options,
        ], $this->filters->filterableQuestions($organizationId));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function placementApplications(string $organizationId, array $filters, string $timezone): array
    {
        Gate::authorize('assignAny', RegistrationApplication::class);
        $query = RegistrationApplication::query()->forOrganization($organizationId)
            ->with(['registrationForm', 'studentProfile:id,student_code'])
            ->whereIn('status', [RegistrationStatus::WaitingAssignment, RegistrationStatus::Assigned]);
        if (!empty($filters['application'])) {
            $owned = RegistrationApplication::query()->forOrganization($organizationId)->findOrFail($filters['application']);
            Gate::authorize('assign', $owned);
            $query->whereKey($owned->id);
        } else {
            if (($filters['status'] ?? 'waiting') !== 'all') {
                $query->where('status', ($filters['status'] ?? 'waiting') === 'assigned' ? RegistrationStatus::Assigned : RegistrationStatus::WaitingAssignment);
            }
            if (!empty($filters['course']) && ($filters['scope'] ?? 'course') === 'course') {
                $query->where(fn (Builder $q) => $q->where('preferred_course_id', $filters['course'])->orWhereNull('preferred_course_id'));
            }
        }
        if (!empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }
        $page = $query->latest('created_at')->paginate((int) config('console.directory_per_page'))->withQueryString();

        return [
            'data' => $page->getCollection()->map(fn (RegistrationApplication $application): array => $this->applicationData($application, $timezone))->all(),
            'total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'next_page_url' => $page->nextPageUrl(), 'prev_page_url' => $page->previousPageUrl(),
        ];
    }

    /** @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public function placementRowsByIds(string $organizationId, array $ids, string $timezone): array
    {
        $this->authorizePlacement($organizationId, $ids);

        return RegistrationApplication::query()->forOrganization($organizationId)->whereKey($ids)
            ->with(['registrationForm', 'studentProfile:id,student_code'])->get()
            ->map(fn (RegistrationApplication $application): array => $this->applicationData($application, $timezone))->all();
    }

    /** @param list<string> $ids */
    public function authorizePlacement(string $organizationId, array $ids): void
    {
        Gate::authorize('assignAny', RegistrationApplication::class);
        $applications = RegistrationApplication::query()->forOrganization($organizationId)->whereKey($ids)->get();
        abort_unless($applications->count() === count(array_unique($ids)), 404);
        foreach ($applications as $application) {
            Gate::authorize('assign', $application);
        }
    }

    /** @return array<string, mixed> */
    private function formData(RegistrationForm $form): array
    {
        return [
            'id' => $form->id, 'title' => $form->title['ar'] ?? '', 'description' => $form->description['ar'] ?? '',
            'slug' => $form->slug, 'is_active' => $form->is_active,
            'preferred_program_id' => $form->preferred_program_id, 'preferred_course_id' => $form->preferred_course_id,
            'applications_count' => (int) ($form->getAttribute('applications_count') ?? 0),
            'public_url' => route('register.student.form', ['formSlug' => $form->slug]),
            'questions' => $form->questions->map(static fn (RegistrationQuestion $question): array => [
                'id' => $question->id, 'question' => $question->question['ar'] ?? '', 'type' => $question->type->value,
                'options' => $question->options ?? [], 'is_required' => $question->is_required,
                'is_active' => $question->is_active, 'is_filterable' => $question->is_filterable,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function applicationData(RegistrationApplication $application, string $timezone): array
    {
        return [
            ...$application->only(['id', 'full_name', 'phone', 'email', 'country_id', 'region_id', 'notes', 'preferred_program_id', 'preferred_course_id', 'student_profile_id', 'user_id', 'decision_reason', 'duplicate_of_application_id']),
            'date_of_birth' => $application->date_of_birth?->toDateString(),
            'age' => $application->date_of_birth?->diffInYears(CarbonImmutable::now($timezone)),
            'gender' => $application->gender->value, 'status' => $application->status->value,
            'submitted_at' => $application->submitted_at?->setTimezone($timezone)->format('Y-m-d H:i'),
            'student_code' => $application->studentProfile?->student_code,
            'source' => $application->registrationForm?->title['ar'] ?? __('console_registration.general_source'),
            'registration_form_id' => $application->registration_form_id,
            'answers' => $application->evaluation_answers ?? [],
            'can_review' => $application->status->canTransitionTo(RegistrationStatus::UnderReview),
            'can_decide' => $application->status->canTransitionTo(RegistrationStatus::Accepted),
            'can_place' => $application->status->isClearedForAssignment(),
        ];
    }
}
