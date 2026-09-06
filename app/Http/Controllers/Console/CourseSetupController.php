<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\ConsoleMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\CourseSetupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Application\Services\ConsoleSetupService as AcademicSetup;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Application\Services\ConsoleSetupService as GroupSetup;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;

final class CourseSetupController extends Controller
{
    public function __construct(
        private readonly AcademicSetup $academics,
        private readonly GroupSetup $groups,
        private readonly TeacherQualificationQueries $qualifications,
        private readonly TeacherDirectoryQueries $teachers,
        private readonly ConsoleContext $context,
        private readonly AcademicCatalogQueries $catalogQueries,
        private readonly Transaction $transaction,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = (string) $user?->organization_id;
        abort_if($organizationId === '', 403);
        $catalog = $this->academics->catalog($organizationId);
        foreach ($catalog['programs'] as &$program) {
            $program['default_rate_amount'] = $program['default_rate'] === null
                ? null : ConsoleMoney::toMajor(Money::of((int) $program['default_rate'], (string) $program['currency']));
            unset($program['default_rate']);
        }
        unset($program);
        $canGroups = $user?->can('group.view') ?? false;
        $teacherOptions = [];
        $qualifiedIds = [];
        if ($user?->can('group.manage')) {
            foreach ($catalog['courses'] as $course) {
                $qualifiedIds[(string) $course['id']] = $this->qualifications->qualifiedTeacherIdsForCourse((string) $course['id']);
            }
            $directory = $this->teachers->directoryFor($organizationId, array_values(array_unique(array_merge(...array_values($qualifiedIds)))));
            foreach ($qualifiedIds as $courseId => $ids) {
                $teacherOptions[$courseId] = array_values(array_map(
                    static fn ($teacher): array => ['id' => $teacher->staffProfileId, 'name' => $teacher->name],
                    array_intersect_key($directory, array_flip($ids)),
                ));
            }
        }
        if (!$user?->can('program.manage')) {
            $catalog['programs'] = array_map(
                static fn (array $program): array => Arr::only($program, ['id', 'name', 'label', 'code', 'is_active']),
                $catalog['programs'],
            );
        }
        $tab = $request->routeIs('console.groups.index') ? 'groups'
            : ($request->routeIs('console.courses.programs') ? 'programs' : $request->query('tab', 'courses'));

        $allowedTabs = array_keys(array_filter([
            'courses' => $user?->can('course.manage'),
            'programs' => $user?->can('program.manage'),
            'groups' => $canGroups,
        ]));
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = $allowedTabs[0] ?? 'courses';
        }

        $groupRows = $canGroups ? $this->groups->groups($organizationId) : [];
        $initialGroup = collect($groupRows)->firstWhere('id', $request->query('group'));
        $initialCourse = is_array($initialGroup) ? collect($catalog['courses'])->first(function (array $course) use ($request, $initialGroup): bool {
            return $course['id'] === $request->query('course')
                && in_array($course['program_id'] ?? null, $initialGroup['program_ids'], true)
                && ($course['is_active'] ?? false) && ($course['session_mode'] ?? null) !== 'individual';
        }) : null;

        return Inertia::render('Console/Courses', [
            ...$catalog,
            'groups' => $groupRows,
            'teacherOptions' => $teacherOptions,
            'initialTab' => in_array($tab, ['courses', 'programs', 'groups'], true) ? $tab : 'courses',
            'initialGroupAction' => $request->query('action') === 'assign' ? 'assign' : null,
            'initialGroupId' => $initialGroup['id'] ?? null,
            'initialCourseId' => $initialCourse['id'] ?? null,
            'abilities' => [
                'programs' => $user?->can('program.manage') ?? false,
                'courses' => $user?->can('course.manage') ?? false,
                'groups' => $user?->can('group.manage') ?? false,
                'viewGroups' => $canGroups,
                'registration' => $user?->can('student.create') ?? false,
            ],
            'defaults' => [
                'timezone' => $this->context->forRequest($request)['school']['timezone'],
                'currency' => config('payroll.currency'),
                'sessionMinutes' => config('academics.session_minutes.default'),
                'capacityMin' => config('groups.capacity.minimum'),
                'capacityMax' => config('groups.capacity.maximum'),
                'today' => now('UTC')->toDateString(),
            ],
        ]);
    }

    public function save(CourseSetupRequest $request): RedirectResponse
    {
        $organizationId = (string) $request->user()?->organization_id;
        abort_if($organizationId === '', 403);
        $kind = (string) $request->route('kind');
        $id = $request->route('id');
        $id = is_string($id) ? $id : null;
        $reason = __('console_courses.audit.'.($id === null ? 'create_' : 'update_').$kind);
        try {
            [$savedId, $groupId] = $this->transaction->run(function () use ($request, $organizationId, $id, $kind, $reason): array {
                $savedId = match ($kind) {
                    'programs' => $this->academics->saveProgram($organizationId, $id, $request->actionData(), (string) $request->user()?->getAuthIdentifier(), $reason),
                    'levels' => $this->academics->saveLevel($organizationId, $id, $request->actionData(), (string) $request->user()?->getAuthIdentifier(), $reason),
                    default => $this->academics->saveCourse($organizationId, $id, $request->actionData(), (string) $request->user()?->getAuthIdentifier(), $reason),
                };
                $groupId = null;
                if ($kind === 'items' && $id === null && $request->filled('first_group_name')) {
                    $course = $this->catalogQueries->coursesByIds($organizationId, [$savedId])[$savedId] ?? null;
                    abort_if($course === null || $course->programId === null, 422);
                    $groupId = $this->groups->save($organizationId, null, [
                        'code' => 'G-'.Str::ulid(),
                        'name' => ['ar' => $request->validated('first_group_name')],
                        'capacity' => $request->validated('first_group_capacity'),
                        'starts_on' => $request->validated('first_group_starts_on'),
                        'ends_on' => $request->validated('first_group_ends_on'),
                        'timezone' => $this->context->forRequest($request)['school']['timezone'],
                        'program_ids' => [$course->programId],
                    ], (string) $request->user()?->getAuthIdentifier(), __('console_courses.audit.create_groups'));
                }

                return [$savedId, $groupId];
            });
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }

        if ($groupId !== null) {
            return redirect()->route('console.groups.index', ['group' => $groupId, 'course' => $savedId, 'action' => 'assign'])
                ->with('success', __('console_courses.saved_course_group'));
        }

        return redirect()->route($kind === 'items' ? 'console.courses.index' : 'console.courses.programs', ['focus' => $savedId])
            ->with('success', __('console_courses.saved'));
    }
}
