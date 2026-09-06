<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Queries\ProfileAdministrationQueryService;
use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\RegistrationDecisionRequest;
use App\Http\Requests\Console\RegistrationFormRequest;
use App\Http\Requests\Console\RegistrationIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Application\Actions\AcceptPublicRegistrationAction;
use Modules\Students\Application\Services\ConsoleRegistrationService;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Shared\Support\BusinessRuleViolation;

final class RegistrationController extends Controller
{
    public function __construct(
        private ConsoleRegistrationService $registrations,
        private AcademicCatalogQueries $catalog,
        private GeographyQueries $geography,
        private ConsoleContext $context,
        private ProfileAdministrationQueryService $profiles,
        private UserAccountDirectory $accounts,
        private AcceptPublicRegistrationAction $accept,
    ) {}

    public function index(RegistrationIndexRequest $request): Response
    {
        $organizationId = $this->organizationId($request);
        $filters = $request->validated();
        $filters['stage'] ??= 'forms';
        $courseId = $filters['course'] ?? null;
        $catalog = $this->catalog($organizationId);
        if ($courseId !== null) {
            abort_unless(in_array($courseId, array_column($catalog, 'id'), true), 404);
        }
        $timezone = (string) $this->context->forRequest($request)['timezone'];
        $questions = $this->registrations->questionsForFiltering($organizationId);
        if (isset($filters['question'])) {
            abort_unless(in_array($filters['question'], array_column($questions, 'id'), true), 404);
        }

        $applications = $filters['stage'] === 'forms' ? null : $this->registrations->applications($organizationId, $filters, $timezone);
        if ($applications !== null) {
            $applications['data'] = array_map(fn (array $item): array => [...$item, 'placement_url' => $this->placementLink($request, $item, $catalog)], $applications['data']);
        }

        return Inertia::render('Console/Registration', [
            'filters' => $filters, 'catalog' => $catalog,
            'catalogAbilities' => [
                'courses' => $request->user()?->can('course.manage') ?? false,
                'groups' => $request->user()?->can('group.view') ?? false,
                'programs' => $request->user()?->can('program.manage') ?? false,
            ],
            'forms' => $this->registrations->forms($organizationId, $courseId),
            'counts' => $this->registrations->counts($organizationId, $courseId),
            'applications' => $applications,
            'questionFilters' => $questions,
            'countries' => $this->countries(),
            'statuses' => array_map(static fn (RegistrationStatus $status): array => ['id' => $status->value, 'name' => $status->label()], RegistrationStatus::cases()),
            'publicEnabled' => (bool) config('admission.self_registration.enabled'),
            'canPlace' => $request->user()?->can('student.view.any') && $request->user()->can('enrollment.create') && $request->user()->can('group.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $organizationId = $this->organizationId($request);
        $catalog = $this->catalog($organizationId);
        $courseId = $request->query('course');
        $course = collect($catalog)->firstWhere('id', $courseId);
        if ($courseId !== null) {
            abort_if($course === null, 404);
        }
        $form = [
            'id' => null, 'title' => '', 'description' => '', 'slug' => 'register-'.Str::lower(Str::random(10)), 'is_active' => false, 'questions' => [],
            'preferred_course_id' => $course['id'] ?? null, 'preferred_program_id' => $course['program_id'] ?? null,
        ];
        if (is_string($request->query('template'))) {
            $template = $this->registrations->form($organizationId, $request->string('template')->toString());
            $form = [...$template, 'id' => null, 'slug' => 'register-'.Str::lower(Str::random(10)), 'is_active' => false, 'public_url' => null,
                'title' => $template['title'].' — '.__('console_registration.copy'),
                'questions' => array_map(static fn (array $question): array => [...$question, 'id' => null], $template['questions']),
            ];
            if ($course !== null) {
                $form['preferred_course_id'] = $course['id'];
                $form['preferred_program_id'] = $course['program_id'];
            }
        }

        return $this->editor($form, $catalog);
    }

    public function edit(Request $request, string $form): Response
    {
        return $this->editor($this->registrations->form($this->organizationId($request), $form), $this->catalog($this->organizationId($request)));
    }

    public function store(RegistrationFormRequest $request): RedirectResponse
    {
        $id = $this->registrations->save($this->organizationId($request), null, $request->validated(), (string) $request->user()?->getAuthIdentifier());

        return redirect()->route('console.registration.forms.edit', ['form' => $id])->with('success', __('console_registration.saved'));
    }

    public function update(RegistrationFormRequest $request, string $form): RedirectResponse
    {
        $this->registrations->save($this->organizationId($request), $form, $request->validated(), (string) $request->user()?->getAuthIdentifier());

        return back()->with('success', __('console_registration.saved'));
    }

    public function show(Request $request, string $application): Response
    {
        $organizationId = $this->organizationId($request);
        $context = $this->context->forRequest($request);
        $item = $this->registrations->application($organizationId, $application, (string) $context['timezone']);
        $matches = [];
        foreach (array_filter([$item['email'], $item['phone']]) as $term) {
            foreach ($this->accounts->search($organizationId, (string) $term, (int) config('identity.directory.max_results')) as $account) {
                $matches[$account->id] = ['id' => $account->id, 'name' => $account->name, 'username' => $account->username, 'phone' => $account->phone, 'email' => $account->email];
            }
        }
        if ($item['user_id'] !== null && ($account = $this->accounts->find($organizationId, $item['user_id'])) !== null) {
            $matches[$account->id] = ['id' => $account->id, 'name' => $account->name, 'username' => $account->username, 'phone' => $account->phone, 'email' => $account->email];
        }

        return Inertia::render('Console/RegistrationReview', [
            'placementUrl' => $this->placementLink($request, $item, $this->catalog($organizationId)),
            'application' => $item, 'catalog' => $this->catalog($organizationId), 'countries' => $this->countries(),
            'accounts' => array_values($matches), 'usernameSuggestions' => $this->profiles->usernameSuggestions($organizationId, $item['full_name']),
            'canPlace' => $request->user()?->can('student.view.any') && $request->user()->can('enrollment.create') && $request->user()->can('group.manage'),
            'timezone' => $context['school']['timezone'], 'passwordLength' => (int) config('admission.account.generated_password_length'),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        return response()->json(['accounts' => $search === '' ? [] : $this->profiles->accountOptions($this->organizationId($request), mb_substr($search, 0, 255))]);
    }

    public function decide(RegistrationDecisionRequest $request, string $application): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();
        try {
            match ($data['decision']) {
                'accept' => $this->accept->execute($organizationId, $application, $actorId, $data),
                'review' => $this->registrations->review($organizationId, $application, $actorId),
                default => $this->registrations->reject($organizationId, $application, $actorId,
                    __('console_registration.rejections.'.$data['rejection_category']).(empty($data['note']) ? '' : ' — '.$data['note'])),
            };
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }

        return redirect()->route('console.registration.applications.show', ['application' => $application])->with('success', __('console_registration.decision_saved'));
    }

    /**
     * @param array<string, mixed> $form
     * @param list<array<string, mixed>> $catalog
     */
    private function editor(array $form, array $catalog): Response
    {
        return Inertia::render('Console/RegistrationEditor', [
            'form' => $form, 'catalog' => $catalog,
            'questionTypes' => array_map(static fn (RegistrationQuestionType $type): array => ['id' => $type->value, 'name' => $type->label(), 'has_options' => $type->hasOptions(), 'filterable' => $type->canBeFiltered()], RegistrationQuestionType::cases()),
            'publicEnabled' => (bool) config('admission.self_registration.enabled'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function catalog(string $organizationId): array
    {
        $courses = [];
        foreach ($this->catalog->programs($organizationId) as $program) {
            foreach ($this->catalog->courses($organizationId, $program->id) as $course) {
                $courses[] = ['id' => $course->id, 'name' => $course->name['ar'] ?? $course->code, 'program_id' => $program->id, 'program_name' => $program->name['ar'] ?? $program->code, 'is_quran' => $course->code === (string) config('scheduling.individual_quran.course_code') && in_array($course->sessionMode, ['individual', 'both'], true)];
            }
        }

        return $courses;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $catalog
     */
    private function placementLink(Request $request, array $item, array $catalog): ?string
    {
        if (!$item['can_place'] || $item['student_profile_id'] === null) {
            return null;
        }
        $course = collect($catalog)->firstWhere('id', $item['preferred_course_id']);
        $quran = (bool) ($course['is_quran'] ?? false);
        $permissions = $quran ? ['student.view.any', 'student.view', 'enrollment.create', 'schedule.manage']
            : ['student.view.any', 'enrollment.create', 'group.manage'];
        if (!collect($permissions)->every(fn (string $permission): bool => $request->user()?->can($permission) ?? false)) {
            return null;
        }

        return $quran
            ? '/manage/quran?'.http_build_query(['student' => $item['student_profile_id'], 'application' => $item['id'], 'tab' => 'students'])
            : '/manage/placement?'.http_build_query(array_filter(['application' => $item['id'], 'course' => $item['preferred_course_id']]));
    }

    /** @return list<array<string, string>> */
    private function countries(): array
    {
        return array_map(static fn ($country): array => ['id' => $country->id, 'name' => $country->name['ar'] ?? $country->iso2], $this->geography->countries());
    }

    private function organizationId(Request $request): string
    {
        $id = (string) data_get($request->user(), 'organization_id');
        abort_if($id === '', 403);

        return $id;
    }
}
