<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\GroupScheduleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Scheduling\Application\Services\ConsoleGroupScheduleService;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
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
        $filters = $request->validate(['group' => ['nullable', 'ulid'], 'course' => ['nullable', 'ulid']]);
        $clock = $this->clock->forOrganization($this->organization($request));

        return $this->editor($request, [
            'id' => null, 'group_id' => $filters['group'] ?? '', 'course_id' => $filters['course'] ?? '', 'staff_profile_id' => '',
            'weekdays' => [], 'start_time' => '', 'duration_minutes' => (int) config('scheduling.default_duration_minutes'),
            'interval_weeks' => 1, 'timezone' => $clock['timezone'], 'starts_on' => now($clock['timezone'])->toDateString(), 'ends_on' => '',
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

    private function persist(GroupScheduleRequest $request, ?string $schedule): RedirectResponse
    {
        try {
            $id = $this->schedules->save($this->organization($request), $schedule, $request->validated(), (string) $request->user()?->getAuthIdentifier());
        } catch (BusinessRuleViolation $error) {
            throw ValidationException::withMessages(['form' => $error->getMessage()]);
        }

        return redirect()->route('console.schedules.edit', ['schedule' => $id])->with('success', __('console_sessions.saved'));
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
            && collect((array) $group['assignments'])->contains(fn (array $assignment): bool => $assignment['teacher_id'] === $data['staff_profile_id'] && $assignment['course_id'] === $data['course_id']), 404);
    }

    private function organization(Request $request): string
    {
        $id = (string) data_get($request->user(), 'organization_id');
        abort_if($id === '', 403);

        return $id;
    }
}
