<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use App\Application\Actions\PlaceConsoleQuranStudentAction;
use App\Application\Queries\QuranWorkspaceQueries;
use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\SaveQuranPlacementRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Scheduling\Application\Services\ConsoleQuranScheduleService;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Application\Actions\SupersedeTeacherRate;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Students\Application\Services\ConsoleQuranRegistrationService;
use Modules\Students\Application\Services\ConsoleRegistrationService;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;

final class QuranController extends Controller
{
    public function __construct(
        private readonly BulkCreateIndividualQuranSchedulesAction $placement,
        private readonly StudentDirectoryQueries $students,
        private readonly UserQueryService $users,
        private readonly TeacherDirectoryQueries $teacherDirectory,
        private readonly ConsoleContext $context,
        private readonly AcademicCatalogQueries $catalog,
        private readonly QuranWorkspaceQueries $workspace,
        private readonly ConsoleQuranScheduleService $schedules,
        private readonly TeacherAvailabilityPlanner $planner,
        private readonly ConsoleRegistrationService $registration,
        private readonly ConsoleQuranRegistrationService $quranRegistrations,
        private readonly PlaceConsoleQuranStudentAction $placeStudent,
        private readonly StaffQueries $staff,
    ) {}

    public function index(Request $request): Response
    {
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        $filters = $request->validate([
            'phase' => ['sometimes', Rule::in(['before', 'during', 'after'])],
            'tab' => ['sometimes', Rule::in(['registration', 'students', 'teachers', 'schedule', 'policy'])],
            'status' => ['sometimes', Rule::in(['pending', 'assigned', 'issues', 'all'])],
            'search' => ['nullable', 'string', 'max:200'],
            'application' => ['nullable', 'ulid'],
            'teacher' => ['nullable', 'ulid'], 'student' => ['nullable', 'ulid'],
            'history' => ['sometimes', Rule::in(['0', '1'])],
            'period' => ['sometimes', 'date_format:Y-m'], 'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $timezone = (string) $this->context->forRequest($request)['school']['timezone'];
        $filters = [...['phase' => 'before', 'tab' => 'students', 'status' => 'all', 'search' => '', 'teacher' => null, 'student' => null, 'application' => null, 'history' => '0', 'period' => now($timezone)->format('Y-m')], ...$filters];
        $course = $this->course($organizationId);
        $filters['search'] = trim((string) $filters['search']);
        $can = [
            'place' => $request->user()?->can('schedule.manage') ?? false,
            'sessions' => $request->user()?->can('session.view') ?? false,
            'attendance' => $request->user()?->can('attendance.view') ?? false,
            'progress' => $request->user()?->can('session_report.view') ?? false,
            'registration' => $request->user()?->can('student.create') ?? false,
            'teacherProfile' => $request->user()?->can('staff.view.any') ?? false,
            'availability' => ($request->user()?->can('staff.view') && $request->user()->can('staff.view.any')),
            'settings' => $request->user()?->can('organizations.view') ?? false,
            'dues' => (bool) config('features.payroll') && ($request->user()?->can('payroll.view') ?? false),
            'followup' => collect(['enrollment.view', 'attendance.view', 'discipline.view_any'])->every(fn (string $ability): bool => $request->user()?->can($ability) ?? false),
        ];
        $active = $this->placement->activeScheduleSummariesByStudent($organizationId);
        $eligibleIds = $this->placement->individualQuranStudentIds($organizationId);
        $waiting = $course === null ? [] : $this->quranRegistrations->waiting($organizationId, $course->id, $filters['application']);
        abort_if($filters['application'] !== null && !in_array($filters['application'], array_column($waiting, 'id'), true), 404);
        $enrollments = $course?->programId !== null && $request->user()?->can('enrollment.view')
            ? $this->workspace->enrollments($organizationId, $course->programId) : [];
        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $filters['period'], $timezone)->startOfMonth();
        $activity = $course !== null && $can['sessions']
            ? $this->workspace->activity($organizationId, $course->id, $periodStart->utc(), $periodStart->addMonth()->utc(), $timezone, $can['attendance'], $can['progress'])
            : ['students' => [], 'sessions' => []];
        $ids = array_values(array_unique([...$eligibleIds, ...array_column($waiting, 'student_id'), ...array_keys($active), ...array_column($enrollments, 'student_id'), ...array_keys($activity['students'])]));
        $profiles = $this->students->byIds($organizationId, $ids);
        $accounts = $this->users->summariesByIds(array_values(array_map(static fn ($profile): string => $profile->userId, $profiles)));
        $qualifiedTeachers = $this->placement->teacherOptions($organizationId);
        $pendingTeachers = $course === null ? collect() : PendingTeachingAssignment::query()
            ->where('organization_id', $organizationId)->where('course_id', $course->id)->get()->groupBy('student_profile_id');
        $teacherNames = $qualifiedTeachers;
        $teacherIds = array_values(array_unique([...array_keys($qualifiedTeachers), ...array_column($active, 'staff_profile_id'), ...array_column($activity['sessions'], 'teacher_id')]));
        foreach ($this->teacherDirectory->directoryFor($organizationId, $teacherIds) as $teacher) {
            $teacherNames[$teacher->staffProfileId] ??= $teacher->name;
        }
        $enrollmentsByStudent = [];
        foreach ($enrollments as $enrollment) {
            $enrollmentsByStudent[$enrollment['student_id']][] = $enrollment;
        }
        $rows = [];
        foreach ($profiles as $profile) {
            $account = $accounts[$profile->userId] ?? null;
            if ($account === null || !hash_equals($organizationId, $account->organizationId)) {
                continue;
            }
            $schedule = $active[$profile->id] ?? null;
            $pendingApplications = array_values(array_filter($waiting, static fn (array $item): bool => $item['student_id'] === $profile->id));
            $selectedApplication = collect($pendingApplications)->firstWhere('id', $filters['application']) ?? ($pendingApplications[0] ?? null);
            $studentEnrollments = $enrollmentsByStudent[$profile->id] ?? [];
            $insight = $activity['students'][$profile->id] ?? null;
            $held = !$account->isActive() || count(array_filter($studentEnrollments, static fn (array $item): bool => $item['held'])) > 0;
            $rows[] = [
                'id' => $profile->id, 'code' => $profile->studentCode, 'name' => $account->name,
                'student_timezone' => $account->timezone, 'schedule' => $schedule,
                'pending_teacher_ids' => $pendingTeachers->get($profile->id, collect())->pluck('staff_profile_id')->all(),
                'teacher_name' => $schedule === null ? ($pendingTeachers->has($profile->id) ? $pendingTeachers[$profile->id]->map(fn ($link) => $teacherNames[$link->staff_profile_id] ?? __('console_quran.teacher_unavailable'))->implode(' / ').' — '.__('learning.awaiting_schedule') : null) : ($teacherNames[(string) $schedule['staff_profile_id']] ?? __('console_quran.teacher_unavailable')),
                'held' => $held, 'can_schedule' => $account->isActive() && (in_array($profile->id, $eligibleIds, true) || $selectedApplication !== null),
                'application_id' => $selectedApplication['id'] ?? null, 'enrollments' => $studentEnrollments, 'insight' => $insight,
                'needs_followup' => $held || ($insight['absences'] ?? 0) > 0,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['code'], $b['code']));
        abort_if($filters['student'] !== null && !in_array($filters['student'], array_column($rows, 'id'), true), 404);
        abort_if($filters['teacher'] !== null && !array_key_exists($filters['teacher'], $teacherNames), 404);
        $counts = ['all' => count($rows), 'assigned' => count(array_filter($rows, static fn (array $row): bool => $row['schedule'] !== null))];
        $counts['pending'] = count(array_filter($rows, static fn (array $row): bool => $row['schedule'] === null && $row['can_schedule']));
        $counts['issues'] = count(array_filter($rows, static fn (array $row): bool => $row['needs_followup']));
        $filtered = array_values(array_filter($rows, static fn (array $row): bool => ($filters['status'] === 'all' || ($filters['status'] === 'issues' ? $row['needs_followup'] : (($filters['status'] === 'assigned') === ($row['schedule'] !== null))))
            && ($filters['status'] !== 'pending' || $row['can_schedule'])
            && ($filters['search'] === '' || mb_stripos($row['name'].' '.$row['code'], (string) $filters['search']) !== false)
            && ($filters['teacher'] === null || (($row['schedule']['staff_profile_id'] ?? null) === $filters['teacher'] || ($row['schedule'] === null && in_array($filters['teacher'], $row['pending_teacher_ids'], true))))));
        $availability = ($can['place'] || $request->user()?->can('schedule.view')) ? $this->workspace->availability($organizationId, array_keys($teacherNames)) : [];
        $teacherRows = [];
        foreach ($teacherNames as $id => $name) {
            $teacherRows[] = ['id' => $id, 'name' => $name, 'qualified' => array_key_exists($id, $qualifiedTeachers),
                'student_ids' => array_values(array_column(array_filter($rows, static fn (array $row): bool => (($row['schedule']['staff_profile_id'] ?? null) === $id || ($row['schedule'] === null && in_array($id, $row['pending_teacher_ids'], true)))), 'id')),
                'availability' => $availability[$id] ?? [],
                'sessions' => count(array_filter($activity['sessions'], static fn (array $item): bool => $item['teacher_id'] === $id && $item['status'] !== SessionStatus::Superseded->value)),
            ];
        }
        $sessionRows = array_values(array_filter($activity['sessions'], static fn (array $row): bool => ($filters['history'] === '1' || $row['status'] !== SessionStatus::Superseded->value)
            && ($filters['teacher'] === null || $row['teacher_id'] === $filters['teacher'])
            && ($filters['student'] === null || $row['student_id'] === $filters['student'])));

        return Inertia::render('Console/Quran', [
            'students' => $this->paginate($filtered, $request), 'directory' => $rows,
            'sessions' => $this->paginate($sessionRows, $request), 'teacherRows' => $teacherRows,
            'teachers' => $can['place'] ? $qualifiedTeachers : [], 'counts' => $counts, 'filters' => $filters,
            'canPlace' => $can['place'], 'can' => $can,
            'course' => $course === null ? null : ['id' => $course->id, 'name' => $course->name['ar'] ?? $course->code],
            'registration' => $can['registration'] && $course !== null ? [
                'forms' => $this->registration->forms($organizationId, $course->id),
                'counts' => $this->registration->counts($organizationId, $course->id),
            ] : null,
            'policy' => [
                'edit_lock_hours' => (int) config('scheduling.recurrence.edit_lock_hours'),
                'cancel_minutes' => (int) config('scheduling.notice.cancellation_minutes'),
                'postpone_minutes' => (int) config('scheduling.notice.postponement_minutes'),
                'requires_declared' => (bool) config('scheduling.availability.individual_requires_declared'),
                'counter_days' => (int) config('discipline.counter_window_days'),
                'ladder' => array_map(static fn (array $item): array => ['threshold' => $item['threshold'], 'action' => $item['action']], (array) config('discipline.ladder')),
            ],
            'defaults' => [
                'duration_minutes' => (int) config('scheduling.default_individual_duration_minutes'),
                'interval_weeks' => 1, 'timezone' => $timezone, 'starts_on' => now($timezone)->toDateString(), 'ends_on' => '',
                'durations' => array_values((array) config('scheduling.individual_session_durations')),
                'duration_limits' => [
                    'min' => (int) config('session_pay.min_duration'),
                    'max' => (int) config('session_pay.max_duration'),
                ],
                'max_interval' => (int) config('scheduling.individual_quran.max_interval_weeks'),
                'time_step' => (int) config('scheduling.booking_slots.interval_minutes') * 60,
            ],
        ]);
    }

    public function store(SaveQuranPlacementRequest $request, string $student): JsonResponse|RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        $profile = $this->students->find($organizationId, $student);
        abort_if($organizationId === '' || $profile === null, 404);
        $account = $this->users->findSummary($profile->userId);
        if ($account === null || $account->organizationId !== $organizationId || !$account->isActive()) {
            throw ValidationException::withMessages(['placement' => __('scheduling::errors.student_not_schedulable')]);
        }
        $course = $this->course($organizationId);
        abort_if($course === null || $course->programId === null, 404);
        $programId = $course->programId;
        $courseId = $course->id;
        $data = $request->validated();
        try {
            $id = DB::transaction(function () use ($organizationId, $student, $programId, $courseId, $data, $request): string {
                $this->recordRate($request, $courseId, $programId, (string) $data['staff_profile_id'], (string) $data['starts_on']);

                return $this->placeStudent->execute($organizationId, $student, $programId, $courseId, $data, (string) $request->user()?->getAuthIdentifier());
            });
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['placement' => $exception->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['message' => __('console_quran.saved'), 'schedule' => [...$data, 'id' => $id]])
            : back()->with('success', __('console_quran.saved'));
    }

    public function update(SaveQuranPlacementRequest $request, string $student, string $schedule): JsonResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        $course = $this->course($organizationId);
        abort_if($course === null, 404);
        $courseId = $course->id;
        $programId = $course->programId;
        $data = $request->validated();
        try {
            $saved = DB::transaction(function () use ($organizationId, $student, $schedule, $courseId, $programId, $data, $request): array {
                $this->recordRate($request, $courseId, $programId, (string) $data['staff_profile_id'], (string) $data['starts_on']);

                return $this->schedules->update($organizationId, $student, $schedule, $courseId, $data, (string) $request->user()?->getAuthIdentifier());
            });
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['placement' => $exception->getMessage()]);
        }

        return response()->json(['message' => __('console_quran.updated'), 'schedule' => $saved]);
    }

    /** سعر المعلم الساري لهذا الكورس، ليُعرض عند اختيار مدة خارج كتالوج الحصص الفردية. */
    public function rate(Request $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        $input = $request->validate(['staff_profile_id' => ['required', 'string', 'size:26']]);
        $course = $this->course($organizationId);
        $today = CarbonImmutable::now('UTC');
        $rate = $course?->programId === null ? null : app(TeacherRateResolver::class)->resolve(
            $input['staff_profile_id'], $today, $course->programId, $course->id, 'individual',
        );

        return response()->json([
            'rate_major' => $rate === null ? null : $rate['money']->toMajor(),
            'currency' => $rate === null ? (string) config('staff.currency.default', 'EGP') : $rate['money']->currency,
            'requires_rate' => $this->staff->requiresSessionRates($input['staff_profile_id'], $today),
        ]);
    }

    /**
     * سعر حصة المعلم في هذا الكورس عند إرساله مع حفظ التسكين — النطاق بمستوى
     * الكورس بلا تمييز نوع الحصة، فيسري على حصص المجموعات لنفس الكورس أيضًا
     * إن وُجدت. نفس منطق StudentTeacherController::recordRate.
     */
    private function recordRate(SaveQuranPlacementRequest $request, string $courseId, ?string $programId, string $staffProfileId, string $effectiveFrom): void
    {
        $amount = $request->validated('session_rate_major');

        if ($amount === null || (string) $amount === '') {
            return;
        }

        if ($programId === null) {
            throw ValidationException::withMessages([
                'session_rate_major' => __('console_quran.rates.course_unavailable'),
            ]);
        }

        $requested = number_format((float) $amount, 2, '.', '');
        $current = app(TeacherRateResolver::class)->resolve(
            $staffProfileId,
            CarbonImmutable::parse($effectiveFrom, 'UTC'),
            $programId,
            $courseId,
            'individual',
        );

        if ($current !== null
            && $current['scope'] === RateScope::Course
            && number_format((float) $current['money']->toMajor(), 2, '.', '') === $requested) {
            return;
        }

        try {
            app(SupersedeTeacherRate::class)->execute(
                staffProfileId: $staffProfileId,
                scope: RateScope::Course,
                amountMajor: $requested,
                effectiveFrom: $effectiveFrom,
                programId: $programId,
                courseId: $courseId,
                actorId: (string) $request->user()?->getAuthIdentifier(),
                reason: (string) $request->validated('rate_reason'),
            );
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages([
                'session_rate_major' => $violation->getMessage(),
            ]);
        }
    }

    public function availability(SaveQuranPlacementRequest $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        $data = $request->validated();
        abort_unless($organizationId !== '' && array_key_exists((string) $data['staff_profile_id'], $this->placement->teacherOptions($organizationId)), 404);
        $ignore = null;
        if (!empty($data['schedule_id'])) {
            $course = $this->course($organizationId);
            abort_if($course === null, 404);
            $owned = $this->schedules->authorizeAvailability($organizationId, $data['student_id'], $data['schedule_id'], $course->id);
            $ignore = $owned['id'];
        }
        try {
            $result = $this->planner->overview(
                organizationId: $organizationId, staffProfileId: (string) $data['staff_profile_id'],
                weekdays: $data['weekdays'], intervalWeeks: (int) $data['interval_weeks'],
                durationMinutes: (int) $data['duration_minutes'], timezone: (string) $data['timezone'],
                startsOn: (string) $data['starts_on'], endsOn: $data['ends_on'] ?? null,
                requireDeclaredAvailability: (bool) config('scheduling.availability.individual_requires_declared'),
                ignoreScheduleId: $ignore,
            );
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['availability' => $exception->getMessage()]);
        }

        return response()->json(['available_start_times' => $result['available_start_times'], 'has_declared_availability' => $result['has_declared_availability']]);
    }

    private function course(string $organizationId): ?AcademicCatalogItemData
    {
        foreach ($this->catalog->programs($organizationId) as $program) {
            foreach ($this->catalog->courses($organizationId, $program->id) as $course) {
                if ($course->code === (string) config('scheduling.individual_quran.course_code') && in_array($course->sessionMode, ['individual', 'both'], true)) {
                    return $course;
                }
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginate(array $rows, Request $request): LengthAwarePaginator
    {
        $perPage = max(1, (int) config('console.directory_per_page'));
        $page = max(1, min((int) $request->query('page', 1), max(1, (int) ceil(count($rows) / $perPage))));

        return new LengthAwarePaginator(array_slice($rows, ($page - 1) * $perPage, $perPage), count($rows), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
