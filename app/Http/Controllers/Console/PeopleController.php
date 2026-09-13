<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Queries\ProfileAdministrationQueryService;
use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\PersonProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\PeopleStoreRequest;
use App\Http\Requests\Console\PeopleUpdateRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Identity\Domain\Contracts\UserAccountOperations;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\CreateTeacherOnboardingAction;
use Modules\Staff\Application\Actions\UpdateStaffProfileAction;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Application\Actions\CreateStudentOnboardingAction;
use Modules\Students\Application\Actions\UpdateStudentProfileAction;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Codes\EntityCodeGenerator;
use Shared\Support\BusinessRuleViolation;

/** Composition only: profile-owned queries plus public account/catalog DTOs; no cross-module joins. */
final class PeopleController extends Controller
{
    public function __construct(
        private readonly ProfileAdministrationQueryService $profiles,
        private readonly UserAccountDirectory $accounts,
        private readonly UserQueryService $users,
        private readonly GeographyQueries $geography,
    ) {}

    public function index(Request $request, string $kind): Response
    {
        $organizationId = $this->organizationId($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'archived' => ['nullable', 'in:0,1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = $this->query($kind, $organizationId);
        if (($filters['archived'] ?? '0') === '1') {
            $query->onlyTrashed();
        }
        if ($search !== '') {
            $userIds = $this->users->searchUserIdsForOrganization(
                $organizationId, $search, (int) config('console.directory_search_limit', 500),
            );
            $codeColumn = $kind === 'students' ? 'student_code' : 'staff_code';
            $query->where(fn (Builder $where) => $where
                ->where($codeColumn, 'ilike', '%'.$search.'%')
                ->orWhereIn('user_id', $userIds));
        }
        $page = $query->orderByDesc('created_at')
            ->paginate((int) config('console.directory_per_page', 20))->withQueryString();
        $userIds = $page->getCollection()->pluck('user_id')->map(fn ($id): string => (string) $id)->all();
        $accounts = $this->accounts->findMany($organizationId, $userIds);
        $summaries = $this->users->summariesByIds($userIds);
        $canEdit = $request->user()?->can($this->editPermission($kind)) ?? false;
        $canSeeContact = $request->user()?->can('contact.pii.view') ?? false;

        return Inertia::render('Console/People/Index', [
            'kind' => $kind,
            'people' => $page->through(function ($profile) use ($accounts, $summaries, $kind, $canEdit, $canSeeContact): array {
                $account = $accounts[(string) $profile->user_id] ?? null;
                $summary = $summaries[(string) $profile->user_id] ?? null;

                return [
                    'id' => (string) $profile->id,
                    'code' => $this->code($profile),
                    'name' => $account === null ? $this->code($profile) : $account->name,
                    'username' => $account?->username,
                    'phone' => $canSeeContact ? $account?->phone : null,
                    'status' => $profile->trashed() ? __('console_people.archived')
                        : ($account === null ? __('console_people.account_unavailable') : __('identity::status.'.$account->status)),
                    'timezone' => $summary?->timezone,
                    'study' => app(PersonProfileData::class)->studyLabel((string) $profile->organization_id, $kind, (string) $profile->id),
                    'show_url' => route('console.'.$kind.'.show', ['profile' => $profile->id]),
                    'edit_url' => $canEdit && !$profile->trashed()
                        ? route('console.'.$kind.'.edit', ['profile' => $profile->id]) : null,
                ];
            }),
            'filters' => ['search' => $search, 'archived' => $filters['archived'] ?? '0'],
            'indexUrl' => route('console.'.$kind.'.index'),
            'createUrl' => $request->user()?->can($this->createPermission($kind))
                ? route('console.'.$kind.'.create') : null,
        ]);
    }

    public function create(Request $request, string $kind): Response
    {
        $organizationId = $this->organizationId($request);
        $timezone = (string) (Organization::query()->whereKey($organizationId)->value('default_timezone') ?: config('app.timezone'));

        return Inertia::render('Console/People/Form', [
            'kind' => $kind,
            'mode' => 'create',
            'canUpdateAccount' => true,
            'personKinds' => $this->personKinds($request),
            'person' => [
                'account_mode' => 'new', 'locale' => app()->getLocale(), 'timezone' => $timezone,
                'preferred_language' => app()->getLocale(),
                'staff_code' => $kind === 'teachers' ? app(EntityCodeGenerator::class)->next('staff') : '',
                'hired_at' => now($timezone)->toDateString(),
                'contract_effective_from' => now($timezone)->toDateString(),
                'contract_basis' => ContractBasis::PerSession->value,
                'currency' => config('staff.currency.default'),
            ],
            ...$this->formProps($organizationId, $kind),
            'submitUrl' => route('console.'.$kind.'.store'),
            'backUrl' => route('console.'.$kind.'.index'),
        ]);
    }

    public function store(PeopleStoreRequest $request, string $kind): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validated();
        try {
            $profile = $kind === 'students'
                ? app(CreateStudentOnboardingAction::class)->execute(
                    [...$data, 'acceptance_reason' => __('console_people.audit.student_created')],
                    $organizationId, (string) $request->user()?->getAuthIdentifier(),
                )
                : app(CreateTeacherOnboardingAction::class)->execute(
                    [...$data, 'onboarding_reason' => __('console_people.audit.teacher_created')],
                    $organizationId, (string) $request->user()?->getAuthIdentifier(),
                );
        } catch (BusinessRuleViolation $error) {
            $this->businessError($error);
        } catch (QueryException $error) {
            $this->duplicateError($error);
        }

        return to_route('console.'.$kind.'.show', ['profile' => $profile->id])
            ->with('success', __('console_people.created'));
    }

    public function show(Request $request, string $profile, string $kind): Response
    {
        $record = $this->record($request, $kind, $profile);
        Gate::authorize('view', $record);
        $organizationId = $this->organizationId($request);
        $hub = $kind === 'students'
            ? $this->profiles->studentHub($organizationId, (string) $record->id, (string) $record->user_id)
            : $this->profiles->teacherHub($organizationId, (string) $record->id, (string) $record->user_id);
        if (!$request->user()?->can('staff.contract.view')) {
            unset($hub['contracts'], $hub['rates']);
        }
        if (!$request->user()?->can('guardian.view')) {
            unset($hub['guardians']);
        }
        $person = $this->person($record, $organizationId);
        if (!$request->user()?->can('contact.pii.view')) {
            $hub = $this->withoutContactDetails($hub);
            $person['email'] = null;
            $person['phone'] = null;
        }

        return Inertia::render('Console/People/Show', [
            'kind' => $kind,
            'person' => [...$person, 'avatar_url' => app(PersonProfileData::class)->identity($organizationId, (string) $record->user_id)['avatarUrl'] ?? null],
            'financialVisibility' => $record instanceof StaffProfile ? [
                'visible' => $record->financials_visible,
                'updateUrl' => !$record->trashed() && $request->user()?->can('staff.contract.update') ? route('console.teachers.financial-visibility', ['profile' => $record->id]) : null,
            ] : null,
            'lifecycle' => $this->lifecycle($request, $record, $hub),
            'placement' => $record instanceof StudentProfile ? $this->placement($request, $record) : null,
            'hub' => $hub,
            'availabilityUrl' => $kind === 'teachers' && $request->user()?->can('staff.view') && $request->user()->can('staff.view.any') ? route('console.availability.index', ['teacher' => $record->id]) : null,
            'profileWorkspace' => app(PersonProfileData::class)->workspace($request, $organizationId, $kind === 'students' ? 'student' : 'teacher', (string) $record->id, 'admin'),
            'backUrl' => route('console.'.$kind.'.index', $request->only('search', 'archived', 'page')),
            'editUrl' => !$record->trashed() && $request->user()?->can($this->editPermission($kind))
                ? route('console.'.$kind.'.edit', ['profile' => $record->id, ...$request->only('search', 'archived', 'page')]) : null,
            'displayTimezone' => (string) app(ConsoleContext::class)->forRequest($request)['timezone'],
        ]);
    }

    public function edit(Request $request, string $profile, string $kind): Response
    {
        $record = $this->record($request, $kind, $profile);
        Gate::authorize('update', $record);
        abort_if($record->trashed(), 403);
        $organizationId = $this->organizationId($request);

        return Inertia::render('Console/People/Form', [
            'kind' => $kind, 'mode' => 'edit',
            'canUpdateAccount' => Gate::allows('update', User::query()->forOrganization($organizationId)->findOrFail((string) $record->user_id)),
            'person' => $this->person($record, $organizationId),
            ...$this->formProps($organizationId, $kind),
            'submitUrl' => route('console.'.$kind.'.update', ['profile' => $record->id, ...$request->only('search', 'archived', 'page')]),
            'backUrl' => route('console.'.$kind.'.show', ['profile' => $record->id, ...$request->only('search', 'archived', 'page')]),
        ]);
    }

    public function update(PeopleUpdateRequest $request, string $profile, string $kind): RedirectResponse
    {
        $record = $this->record($request, $kind, $profile);
        Gate::authorize('update', $record);
        abort_if($record->trashed(), 403);
        $organizationId = $this->organizationId($request);
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $data = $request->validated();
        $reason = __('console_people.audit.profile_updated');
        try {
            DB::transaction(function () use ($record, $data, $organizationId, $actorId, $reason): void {
                $account = User::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail((string) $record->user_id);
                $identityChanges = [];
                foreach (['full_name' => 'name', 'phone' => 'phone', 'timezone' => 'timezone'] as $input => $attribute) {
                    if (array_key_exists($input, $data) && $data[$input] !== $account->getAttribute($attribute)) {
                        $identityChanges[$attribute] = $data[$input];
                    }
                }
                if ($identityChanges !== []) {
                    Gate::authorize('update', $account);
                    if (array_key_exists('phone', $identityChanges) && $identityChanges['phone'] === null && $account->phone !== null) {
                        throw ValidationException::withMessages(['phone' => __('console_people.phone_replace')]);
                    }
                    app(UserAccountOperations::class)->updateProfile(
                        $organizationId, (string) $record->user_id,
                        $identityChanges, $actorId, $reason,
                    );
                }
                // A shared phone field must not mutate staff data when identity editing is read-only.
                if (!Gate::allows('update', $account)) {
                    unset($data['phone']);
                }
                if ($record instanceof StudentProfile) {
                    app(UpdateStudentProfileAction::class)->execute($record, $data, $actorId, $reason);
                } else {
                    $changes = $data;
                    if (array_key_exists('bio', $changes)) {
                        $changes['bio'] = [...($record->bio ?? []), app()->getLocale() => (string) ($changes['bio'] ?? '')];
                    }
                    app(UpdateStaffProfileAction::class)->execute($record, $changes, $actorId, $reason);
                }
            });
        } catch (BusinessRuleViolation $error) {
            $this->businessError($error);
        } catch (QueryException $error) {
            $this->duplicateError($error);
        }

        return to_route('console.'.$kind.'.show', ['profile' => $record->id, ...$request->only('search', 'archived', 'page')])
            ->with('success', __('console_people.updated'));
    }

    public function options(Request $request, string $kind): JsonResponse
    {
        abort_unless($request->user()?->can($this->createPermission($kind))
            || $request->user()?->can($this->editPermission($kind)), 403);
        $input = $request->validate([
            'country_id' => ['nullable', 'ulid'], 'program_id' => ['nullable', 'ulid'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $organizationId = $this->organizationId($request);
        $country = (string) ($input['country_id'] ?? '');
        $excludedIds = $this->query($kind, $organizationId)->withTrashed()->pluck('user_id')->all();

        return response()->json([
            'regions' => $country === '' ? [] : array_map(fn ($region): array => [
                'value' => $region->id, 'label' => $this->localized($region->name), 'code' => $region->code,
            ], $this->geography->regionsOf($country)),
            'courses' => $this->choices($this->profiles->courseOptions($organizationId, $input['program_id'] ?? null)),
            'accounts' => array_key_exists('search', $input) && $request->user()->can($this->createPermission($kind))
                ? $this->choices($this->profiles->accountOptions($organizationId, (string) $input['search'], $excludedIds)) : [],
        ]);
    }

    public function usernames(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        return response()->json(['suggestions' => $this->profiles->usernameSuggestions(
            $this->organizationId($request), $data['name'],
        )]);
    }

    /**
     * خيارات التسكين المتاحة لهذا المستخدم على هذا الطالب.
     *
     * تُحجب كاملة بلا صلاحية أو على ملف موقوف، فلا يصل الرابط للعميل أصلًا.
     *
     * @return array<string, mixed>|null
     */
    private function placement(Request $request, StudentProfile $record): ?array
    {
        $user = $request->user();

        if ($record->trashed() || $user === null || !$user->can('enrollment.create') || !$user->can('group.manage')) {
            return null;
        }

        $memberships = app(GroupAdministrationQueries::class)
            ->membershipsForStudent((string) $record->organization_id, (string) $record->id);

        return [
            'optionsUrl' => route('console.students.placement-options', ['profile' => $record->id]),
            'addUrl' => route('console.students.placements', ['profile' => $record->id]),
            'transferUrl' => route('console.students.transfer', ['profile' => $record->id]),
            'memberships' => array_values(array_map(
                fn ($membership): array => [
                    'id' => $membership->membershipId,
                    'group' => $this->localized($membership->groupName) ?: $membership->groupCode,
                    'status' => __('groups::status.membership.'.$membership->membershipStatus),
                ],
                array_filter(
                    $memberships,
                    static fn ($membership): bool => $membership->leftAt === null
                        && (MembershipStatus::tryFrom($membership->membershipStatus)?->occupiesSeat() ?? false),
                ),
            )),
        ];
    }

    /**
     * إجراءات دورة حياة الحساب المتاحة فعليًا لهذا المستخدم على هذا السجل.
     *
     * الرابط يُحجب على الخادم عند غياب الصلاحية، فلا يعتمد المنع على إخفاء زر.
     *
     * @param array<string, mixed> $hub
     * @return array<string, mixed>
     */
    private function lifecycle(Request $request, StudentProfile|StaffProfile $record, array $hub): array
    {
        if ($record instanceof StaffProfile) {
            return [
                'archiveUrl' => null, 'restoreUrl' => null, 'enrollments' => [],
                'terminateUrl' => $record->isActive() && Gate::allows('terminate', $record)
                    ? route('console.teachers.terminate', ['profile' => $record->id]) : null,
            ];
        }

        $labels = [];
        foreach (is_array($hub['enrollments'] ?? null) ? $hub['enrollments'] : [] as $row) {
            if (is_array($row) && is_string($row['id'] ?? null)) {
                $labels[$row['id']] = (string) ($row['program'] ?? '');
            }
        }
        $freezable = !$record->trashed() && ($request->user()?->can('enrollment.freeze') ?? false)
            ? app(EnrollmentAdministrationQueries::class)->forStudent((string) $record->organization_id, (string) $record->id)
            : [];

        return [
            'terminateUrl' => null,
            'archiveUrl' => !$record->trashed() && Gate::allows('delete', $record)
                ? route('console.students.archive', ['profile' => $record->id]) : null,
            'restoreUrl' => $record->trashed() && Gate::allows('restore', $record)
                ? route('console.students.restore', ['profile' => $record->id]) : null,
            'enrollments' => array_values(array_filter(array_map(static function ($item) use ($labels): ?array {
                $status = EnrollmentStatus::tryFrom($item->status);

                return $status === null || !$status->canTransitionTo(EnrollmentStatus::Frozen) ? null : [
                    'id' => $item->id,
                    'program' => (string) ($labels[$item->id] ?? $item->programId),
                    'freezeUrl' => route('console.enrollments.freeze', ['enrollment' => $item->id]),
                ];
            }, $freezable))),
        ];
    }

    /** @return array<string, mixed> */
    private function formProps(string $organizationId, string $kind): array
    {
        return [
            'countries' => array_map(fn ($country): array => [
                'value' => $country->id, 'label' => $this->localized($country->name), 'iso2' => $country->iso2,
                'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $country->iso2),
            ], $this->geography->countries()),
            'programs' => $kind === 'students' ? $this->choices($this->profiles->programOptions($organizationId)) : [],
            'courses' => $kind === 'teachers' ? $this->choices($this->profiles->allCourseOptions($organizationId)) : [],
            'timezones' => \DateTimeZone::listIdentifiers(),
            'employmentTypes' => array_map(fn (EmploymentType $type): array => ['value' => $type->value, 'label' => $type->label()], EmploymentType::cases()),
            'contractBases' => array_map(fn (ContractBasis $basis): array => ['value' => $basis->value, 'label' => $basis->label()], ContractBasis::cases()),
            'currencies' => array_values((array) config('staff.currency.supported')),
            'optionsUrl' => route('console.'.$kind.'.options'),
            'usernameUrl' => route('console.'.$kind.'.usernames'),
        ];
    }

    /**
     * يحجب بيانات التواصل على الخادم، فلا تصل للعميل أصلًا بدل إخفائها في الواجهة.
     *
     * @param array<string, mixed> $hub
     * @return array<string, mixed>
     */
    private function withoutContactDetails(array $hub): array
    {
        foreach (['account' => ['email', 'phone'], 'guardians' => ['phone']] as $section => $fields) {
            if (!is_array($hub[$section] ?? null)) {
                continue;
            }

            $hub[$section] = array_map(static function (mixed $row) use ($fields): mixed {
                if (!is_array($row)) {
                    return $row;
                }

                foreach ($fields as $field) {
                    if (array_key_exists($field, $row)) {
                        $row[$field] = null;
                    }
                }

                return $row;
            }, $hub[$section]);
        }

        return $hub;
    }

    /** @return array<string, mixed> */
    private function person(StudentProfile|StaffProfile $profile, string $organizationId): array
    {
        $account = $this->accounts->find($organizationId, (string) $profile->user_id);
        $summary = $this->users->findSummary((string) $profile->user_id);
        $countryId = (string) ($profile->country_id ?? '');
        $countries = $this->geography->countries(false);
        $country = collect($countries)->first(fn ($item): bool => $item->id === $countryId);
        $region = $countryId === '' ? null : collect($this->geography->regionsOf($countryId, false))
            ->first(fn ($item): bool => $item->id === (string) $profile->region_id);
        $common = [
            'id' => (string) $profile->id, 'code' => $this->code($profile), 'full_name' => $account?->name,
            'username' => $account?->username, 'email' => $account?->email, 'phone' => $account?->phone,
            'timezone' => $summary === null ? config('app.timezone') : $summary->timezone,
            'status' => $profile->trashed() ? __('console_people.archived')
                : ($account === null ? __('console_people.account_unavailable') : __('identity::status.'.$account->status)),
            'status_tone' => !$profile->trashed() && $account?->isActive() ? 'active' : 'inactive',
            'archived' => $profile->trashed(), 'date_of_birth' => $profile->date_of_birth?->toDateString(),
            'gender' => $profile->gender?->value, 'country_id' => $profile->country_id,
            'region_id' => $profile->region_id, 'country_name' => $country === null ? null : $this->localized($country->name),
            'region_name' => $region === null ? null : $this->localized($region->name),
        ];

        return $profile instanceof StudentProfile
            ? [...$common, 'city' => $profile->city, 'nationality' => $profile->nationality,
                'preferred_language' => $profile->preferred_language, 'notes' => $profile->notes,
                'joined_at' => $profile->joined_at?->toDateString()]
            : [...$common, 'staff_code' => $profile->staff_code,
                'employment_type' => $profile->employment_type->value,
                'hired_at' => $profile->hired_at?->toDateString(),
                'specializations' => $profile->specializations ?? [],
                'bio' => $this->localized($profile->bio ?? [])];
    }

    private function record(Request $request, string $kind, string $id): StudentProfile|StaffProfile
    {
        return $this->query($kind, $this->organizationId($request))->withTrashed()->whereKey($id)->firstOrFail();
    }

    /** @return Builder<StudentProfile>|Builder<StaffProfile> */
    private function query(string $kind, string $organizationId): Builder
    {
        abort_unless(in_array($kind, ['students', 'teachers'], true), 404);

        return $kind === 'students'
            ? StudentProfile::query()->forOrganization($organizationId)
            : StaffProfile::query()->forOrganization($organizationId);
    }

    private function organizationId(Request $request): string
    {
        $id = $request->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }

    private function code(StudentProfile|StaffProfile $profile): string
    {
        return $profile instanceof StudentProfile ? $profile->student_code : $profile->staff_code;
    }

    private function createPermission(string $kind): string
    {
        return $kind === 'students' ? 'student.create' : 'staff.contract.update';
    }

    /** @return list<array{kind: string, label: string, url: string}> */
    private function personKinds(Request $request): array
    {
        $choices = [];
        foreach (['students', 'teachers'] as $kind) {
            if ($request->user()?->can($this->createPermission($kind))) {
                $choices[] = ['kind' => $kind, 'label' => __('console_people.'.($kind === 'students' ? 'student' : 'teacher')), 'url' => route('console.'.$kind.'.create')];
            }
        }

        return $choices;
    }

    private function editPermission(string $kind): string
    {
        return $kind === 'students' ? 'student.update' : 'staff.contract.update';
    }

    /**
     * @param array<string, string> $values
     * @return list<array{value: string, label: string}>
     */
    private function choices(array $values): array
    {
        return collect($values)->map(fn (string $label, string $value): array => compact('value', 'label'))->values()->all();
    }

    /** @param array<string, mixed> $names */
    private function localized(array $names): string
    {
        return (string) ($names[app()->getLocale()] ?? $names['ar'] ?? $names['en'] ?? reset($names) ?: '');
    }

    private function businessError(BusinessRuleViolation $error): never
    {
        $field = match ($error->rule) {
            'identity.username_taken', 'identity.username_reserved', 'identity.username_required' => 'username',
            'identity.email_taken' => 'email',
            'identity.phone_invalid' => 'phone',
            'registration.region_not_in_country', 'staff.region_country_mismatch', 'students.region_country_mismatch' => 'region_id',
            'registration.offering_invalid' => 'preferred_course_id',
            default => 'form',
        };
        throw ValidationException::withMessages([$field => $error->getMessage()]);
    }

    private function duplicateError(QueryException $error): never
    {
        if ((string) $error->getCode() !== '23505') {
            throw $error;
        }
        $field = str_contains($error->getMessage(), 'username') ? 'username'
            : (str_contains($error->getMessage(), 'staff_code') ? 'staff_code'
                : (str_contains($error->getMessage(), 'email') ? 'email' : 'form'));
        throw ValidationException::withMessages([$field => __('console_people.concurrent_duplicate')]);
    }
}
