<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\GroupScheduleRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Scheduling\Application\Services\ConsoleGroupScheduleService;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Staff\Application\Actions\SupersedeTeacherRate;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Enums\RateScope;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\LocalizedJsonColumn;

final class GroupScheduleController extends Controller
{
    public function __construct(
        private readonly ConsoleGroupScheduleService $schedules,
        private readonly GroupAdministrationQueries $groups,
        private readonly AcademicCatalogQueries $catalog,
        private readonly StaffQueries $staff,
        private readonly TeacherQualificationQueries $qualifications,
        private readonly SchoolClockQueries $clock,
        private readonly TeacherAvailabilityPlanner $planner,
    ) {}

    public function create(Request $request): Response
    {
        // الحقول الاختيارية تسمح بحمل قيم جدول موجود عند إضافة موعد آخر لنفس
        // المجموعة/الكورس/المعلم بيوم ووقت مختلفين، دون إعادة تعبئة الخطوة الأولى.
        $filters = $request->validate([
            'group' => ['nullable', 'ulid'], 'course' => ['nullable', 'ulid'], 'teacher' => ['nullable', 'ulid'],
            'duration' => ['nullable', 'integer', 'min:'.config('session_pay.min_duration'), 'max:'.config('session_pay.max_duration')],
            'interval' => ['nullable', 'integer', 'min:1', 'max:'.config('scheduling.individual_quran.max_interval_weeks')],
            'timezone' => ['nullable', 'timezone:all'],
            'starts' => ['nullable', 'date_format:Y-m-d'], 'ends' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts'],
        ]);
        $clock = $this->clock->forOrganization($this->organization($request));

        return $this->editor($request, [
            'id' => null, 'group_id' => $filters['group'] ?? '', 'course_id' => $filters['course'] ?? '', 'staff_profile_id' => $filters['teacher'] ?? '',
            'weekdays' => [], 'start_time' => '',
            'duration_minutes' => isset($filters['duration']) ? (int) $filters['duration'] : (int) config('scheduling.default_duration_minutes'),
            'interval_weeks' => isset($filters['interval']) ? (int) $filters['interval'] : 1,
            'timezone' => $filters['timezone'] ?? $clock['timezone'],
            'starts_on' => $filters['starts'] ?? now($clock['timezone'])->toDateString(),
            'ends_on' => $filters['ends'] ?? '',
        ]);
    }

    public function edit(Request $request, string $schedule): Response
    {
        return $this->editor($request, $this->schedules->editable($this->organization($request), $schedule));
    }

    public function store(GroupScheduleRequest $request): RedirectResponse
    {
        return $this->persist($request, null);
    }

    public function update(GroupScheduleRequest $request, string $schedule): RedirectResponse
    {
        return $this->persist($request, $schedule);
    }

    public function availability(GroupScheduleRequest $request): JsonResponse
    {
        $organizationId = $this->organization($request);
        $data = $request->validated();
        $options = $this->options($organizationId);
        $this->assertSelection($data, $options);
        if (isset($data['schedule_id'])) {
            $schedule = $this->schedules->editable($organizationId, $data['schedule_id']);
            abort_unless($schedule['group_id'] === $data['group_id'] && $schedule['course_id'] === $data['course_id'], 404);
        }
        try {
            $result = $this->planner->overview(
                organizationId: $organizationId, staffProfileId: $data['staff_profile_id'], weekdays: $data['weekdays'],
                intervalWeeks: (int) $data['interval_weeks'], durationMinutes: (int) $data['duration_minutes'],
                timezone: $data['timezone'], startsOn: $data['starts_on'], endsOn: $data['ends_on'] ?? null,
                selectedStartTime: $data['start_time'], requireDeclaredAvailability: true,
                ignoreScheduleId: $data['schedule_id'] ?? null,
            );
        } catch (BusinessRuleViolation $error) {
            throw ValidationException::withMessages(['form' => $error->getMessage()]);
        }

        return response()->json(['available_start_times' => $result['available_start_times'], 'has_declared_availability' => $result['has_declared_availability']]);
    }

    /** سعر المعلم الساري لهذا الكورس، ليُعرض عند اختيار مدة خارج كتالوج المجموعات. */
    public function rate(Request $request): JsonResponse
    {
        $organizationId = $this->organization($request);
        $input = $request->validate(['course_id' => ['required', 'ulid'], 'staff_profile_id' => ['required', 'string']]);
        $course = $this->catalog->coursesByIds($organizationId, [$input['course_id']])[$input['course_id']] ?? null;
        $today = CarbonImmutable::now('UTC');
        $rate = $course?->programId === null ? null : app(TeacherRateResolver::class)->resolve(
            $input['staff_profile_id'], $today, $course->programId, $input['course_id'], 'group',
        );

        return response()->json([
            'rate_major' => $rate === null ? null : $rate['money']->toMajor(),
            'currency' => $rate === null ? (string) config('staff.currency.default', 'EGP') : $rate['money']->currency,
            'requires_rate' => $this->staff->requiresSessionRates($input['staff_profile_id'], $today),
        ]);
    }

    private function persist(GroupScheduleRequest $request, ?string $schedule): RedirectResponse
    {
        $organizationId = $this->organization($request);
        try {
            $id = DB::transaction(function () use ($request, $organizationId, $schedule): string {
                $this->recordRate(
                    $request, $organizationId,
                    (string) $request->validated('course_id'), (string) $request->validated('staff_profile_id'),
                    (string) $request->validated('starts_on'),
                );

                return $this->schedules->save($organizationId, $schedule, $request->validated(), (string) $request->user()?->getAuthIdentifier());
            });
        } catch (BusinessRuleViolation $error) {
            throw ValidationException::withMessages(['form' => $error->getMessage()]);
        }

        return redirect()->route('console.schedules.edit', ['schedule' => $id])->with('success', __('console_sessions.saved'));
    }

    /**
     * سعر حصة المعلم في هذا الكورس عند إرساله مع حفظ الجدول — النطاق بمستوى
     * الكورس بلا تمييز نوع الحصة (فردي/جماعي)، فيسري على الاثنين لنفس الكورس.
     *
     * نفس منطق StudentTeacherController::recordRate.
     */
    private function recordRate(GroupScheduleRequest $request, string $organizationId, string $courseId, string $staffProfileId, string $effectiveFrom): void
    {
        $amount = $request->validated('session_rate_major');

        if ($amount === null || (string) $amount === '') {
            return;
        }

        $course = $courseId === '' ? null : ($this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null);

        if ($course === null || $course->programId === null) {
            throw ValidationException::withMessages([
                'session_rate_major' => __('console_sessions.rates.course_unavailable'),
            ]);
        }

        $requested = number_format((float) $amount, 2, '.', '');
        $current = app(TeacherRateResolver::class)->resolve(
            $staffProfileId,
            CarbonImmutable::parse($effectiveFrom, 'UTC'),
            $course->programId,
            $courseId,
            'group',
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
                programId: $course->programId,
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

    /** @param array<string, mixed> $schedule */
    private function editor(Request $request, array $schedule): Response
    {
        $organizationId = $this->organization($request);
        $options = $this->options($organizationId);
        if ($schedule['group_id'] !== '') {
            // Existing schedules remain readable when a group later closes; writes still follow the original validator.
            $group = $this->groups->groupsByIds($organizationId, [$schedule['group_id']])[$schedule['group_id']] ?? null;
            abort_if($group === null, 404);
            if (!in_array($group->id, array_column($options['groups'], 'id'), true)) {
                $options['groups'][] = ['id' => $group->id, 'name' => LocalizedJsonColumn::display($group->name), 'code' => $group->code, 'program_ids' => $group->programIds, 'timezone' => $group->timezone, 'assignments' => []];
            }
        }
        if ($schedule['course_id'] !== '') {
            abort_unless(in_array($schedule['course_id'], array_column($options['courses'], 'id'), true), 404);
        }

        return Inertia::render('Console/GroupScheduleEditor', [
            'schedule' => $schedule, ...$options, 'timezones' => timezone_identifiers_list(),
            'durations' => array_values(config('scheduling.session_durations')),
            'durationLimits' => ['min' => (int) config('session_pay.min_duration'), 'max' => (int) config('session_pay.max_duration')],
            'maxInterval' => (int) config('scheduling.individual_quran.max_interval_weeks'),
            'editLockHours' => (int) config('scheduling.recurrence.edit_lock_hours'),
            'outsideAvailability' => (string) config('scheduling.availability.outside_declared'),
            'can' => ['group' => $request->user()?->can('group.view') ?? false, 'sessions' => $request->user()?->can('session.view') && $request->user()->can('student.view.any')],
        ]);
    }

    /** @return array{groups:list<array<string, mixed>>, courses:list<array<string, mixed>>, teachers:list<array<string, mixed>>} */
    private function options(string $organizationId): array
    {
        $courses = [];
        foreach ($this->catalog->programs($organizationId) as $program) {
            foreach ($this->catalog->courses($organizationId, $program->id) as $course) {
                if ($course->sessionMode === 'individual') {
                    continue;
                }
                $courses[] = ['id' => $course->id, 'name' => LocalizedJsonColumn::display($course->name), 'program_id' => $program->id, 'program' => LocalizedJsonColumn::display($program->name),
                    'teachers' => $this->qualifications->qualifiedTeacherIdsForCourse($course->id)];
            }
        }
        $groups = array_map(static fn ($group): array => [
            'id' => $group->id, 'name' => LocalizedJsonColumn::display($group->name), 'code' => $group->code,
            'program_ids' => $group->programIds, 'timezone' => $group->timezone,
            'assignments' => array_map(static fn ($assignment): array => [
                'teacher_id' => $assignment->staffProfileId, 'course_id' => $assignment->courseId,
                'from' => $assignment->assignedFrom, 'until' => $assignment->assignedTo,
            ], $group->teacherAssignments),
        ], $this->groups->activeGroupsForScheduling($organizationId));

        return ['groups' => $groups, 'courses' => $courses, 'teachers' => $this->staff->activeTeacherSummariesForOrganization($organizationId)];
    }

    /** @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    private function assertSelection(array $data, array $options): void
    {
        $group = collect((array) $options['groups'])->firstWhere('id', $data['group_id']);
        $course = collect((array) $options['courses'])->firstWhere('id', $data['course_id']);
        abort_unless($group !== null && $course !== null && in_array($course['program_id'], $group['program_ids'], true), 404);
        abort_unless(in_array($data['staff_profile_id'], array_column($options['teachers'], 'staff_profile_id'), true)
            && in_array($data['staff_profile_id'], $course['teachers'], true)
            && collect((array) $group['assignments'])->contains(fn (array $assignment): bool => $assignment['teacher_id'] === $data['staff_profile_id']
                // تعيين المعلم للمجموعة بلا كورس محدد (course_id فارغ) يعني "كل كورسات المجموعة".
                && ($assignment['course_id'] === $data['course_id'] || $assignment['course_id'] === null)), 404);
    }

    private function organization(Request $request): string
    {
        $id = (string) data_get($request->user(), 'organization_id');
        abort_if($id === '', 403);

        return $id;
    }
}
