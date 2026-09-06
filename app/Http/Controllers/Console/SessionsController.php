<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Scheduling\Application\Services\ConsoleGroupScheduleService;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\LocalizedJsonColumn;

final class SessionsController extends Controller
{
    public function __invoke(Request $request, SessionAdministrationQueries $sessions, GroupAdministrationQueries $groups,
        AcademicCatalogQueries $catalog, StaffQueries $staff, SchoolClockQueries $clock, ConsoleGroupScheduleService $schedules, SessionParticipantAdministrationQueries $participants, StudentDirectoryQueries $students): Response
    {
        $user = $request->user();
        $organizationId = (string) data_get($user, 'organization_id');
        abort_if($organizationId === '', 403);
        $school = $clock->forOrganization($organizationId);
        $filters = $request->validate([
            'view' => ['sometimes', Rule::in(['day', 'week', 'list'])], 'date' => ['sometimes', 'date_format:Y-m-d'],
            'group' => ['nullable', 'ulid'], 'teacher' => ['nullable', 'ulid'], 'course' => ['nullable', 'ulid'],
            'status' => ['nullable', Rule::enum(SessionStatus::class)], 'history' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'timezone:all'], 'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $filters = [...[
            'view' => 'week', 'date' => now($school['timezone'])->toDateString(), 'group' => null, 'teacher' => null, 'course' => null,
            'status' => null, 'history' => false, 'timezone' => data_get($user, 'timezone') ?: $school['timezone'],
        ], ...$filters];
        if ($filters['group'] !== null) {
            abort_unless(isset($groups->groupsByIds($organizationId, [$filters['group']])[$filters['group']]), 404);
        }
        if ($filters['teacher'] !== null) {
            abort_unless(in_array($filters['teacher'], $staff->profileIdsForOrganization($organizationId), true), 404);
        }
        if ($filters['course'] !== null) {
            abort_unless(isset($catalog->coursesByIds($organizationId, [$filters['course']])[$filters['course']]), 404);
        }
        $filters['history'] = (bool) $filters['history'];
        $timezone = $filters['timezone'];
        $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $filters['date'], $timezone);
        $from = $filters['view'] === 'day' ? $anchor : $anchor->startOfWeek($school['week_starts_at']);
        $until = $filters['view'] === 'day' ? $from->addDay() : $from->addWeek();
        $statuses = $filters['status'] !== null ? [$filters['status']] : array_values(array_map(
            static fn (SessionStatus $status): string => $status->value,
            array_filter(SessionStatus::cases(), static fn (SessionStatus $status): bool => (bool) $filters['history'] || $status !== SessionStatus::Superseded),
        ));
        $limit = (int) config('console.directory_search_limit');
        $records = $sessions->forReport($organizationId, $from->utc(), $until->utc(), statuses: $statuses,
            staffProfileId: $filters['teacher'], groupId: $filters['group'], courseId: $filters['course'], limit: $limit + 1);
        $limited = count($records) > $limit;
        $records = array_slice($records, 0, $limit);
        $scheduleRows = $user?->can('schedule.view') ? $schedules->listing($organizationId, $filters['group'], $filters['course']) : [];
        if ($filters['teacher'] !== null) {
            $scheduleRows = array_values(array_filter($scheduleRows, static fn (array $row): bool => $row['staff_profile_id'] === $filters['teacher']));
        }
        $groupIds = array_values(array_unique(array_filter([...array_column($records, 'groupId'), ...array_column($scheduleRows, 'group_id'), $filters['group']])));
        $courseIds = array_values(array_unique([...array_column($records, 'courseId'), ...array_column($scheduleRows, 'course_id')]));
        $teacherIds = array_values(array_unique(array_filter([...array_column($records, 'staffProfileId'), ...array_column($records, 'originalStaffProfileId'), ...array_column($scheduleRows, 'staff_profile_id'), $filters['teacher']])));
        $groupRecords = $groups->groupsByIds($organizationId, $groupIds);
        $courseRecords = $catalog->coursesByIds($organizationId, $courseIds);
        $teacherNames = $staff->namesForProfiles($organizationId, $teacherIds);
        $individuals = $participants->forSessions($organizationId, array_values(array_map(static fn ($item): string => $item->id, array_filter($records, static fn ($item): bool => $item->groupId === ''))));
        $studentIds = [];
        foreach ($individuals as $items) {
            foreach ($items as $item) {
                $studentIds[] = $item->studentProfileId;
            }
        }
        $studentNames = $students->namesForProfiles($organizationId, array_values(array_unique($studentIds)));
        $rows = array_map(static function ($session) use ($timezone, $groupRecords, $courseRecords, $teacherNames, $individuals, $studentNames): array {
            $start = CarbonImmutable::parse($session->scheduledStart)->setTimezone($timezone);
            $end = CarbonImmutable::parse($session->scheduledEnd)->setTimezone($timezone);

            return [
                'id' => $session->id, 'is_quran' => $session->groupId === '' && isset($courseRecords[$session->courseId]) && $courseRecords[$session->courseId]->code === config('scheduling.individual_quran.course_code'), 'title' => LocalizedJsonColumn::display($session->title),
                'course_id' => $session->courseId, 'course' => isset($courseRecords[$session->courseId]) ? LocalizedJsonColumn::display($courseRecords[$session->courseId]->name) : __('console.not_set'),
                'group_id' => $session->groupId, 'group' => isset($groupRecords[$session->groupId]) ? LocalizedJsonColumn::display($groupRecords[$session->groupId]->name) : __('console_sessions.individual'),
                'teacher_id' => $session->staffProfileId, 'teacher' => $teacherNames[$session->staffProfileId] ?? __('console.unassigned'),
                'original_teacher' => $session->originalStaffProfileId === null || $session->originalStaffProfileId === $session->staffProfileId ? null : ($teacherNames[$session->originalStaffProfileId] ?? __('console.not_set')),
                'students' => array_map(static fn ($item): array => ['id' => $item->studentProfileId, 'name' => $studentNames[$item->studentProfileId] ?? __('console.not_set')], $individuals[$session->id] ?? []),
                'date' => $start->toDateString(), 'start' => $start->format('H:i'), 'end' => $end->format('H:i'), 'end_date' => $end->toDateString(),
                'status' => $session->status, 'status_label' => SessionStatus::tryFrom($session->status)?->label() ?? $session->status,
                'actual_start' => $session->actualStart === null ? null : CarbonImmutable::parse($session->actualStart)->setTimezone($timezone)->format('Y-m-d H:i'),
                'actual_end' => $session->actualEnd === null ? null : CarbonImmutable::parse($session->actualEnd)->setTimezone($timezone)->format('Y-m-d H:i'),
            ];
        }, $records);
        $scheduleRows = array_map(static fn (array $row): array => [
            ...$row, 'group' => isset($groupRecords[$row['group_id']]) ? LocalizedJsonColumn::display($groupRecords[$row['group_id']]->name) : __('console.not_set'),
            'course' => isset($courseRecords[$row['course_id']]) ? LocalizedJsonColumn::display($courseRecords[$row['course_id']]->name) : __('console.not_set'),
            'teacher' => $teacherNames[$row['staff_profile_id']] ?? __('console.unassigned'),
        ], $scheduleRows);
        $days = [];
        for ($date = $from; $date->lessThan($until); $date = $date->addDay()) {
            $days[] = ['date' => $date->toDateString(), 'weekday' => $date->dayOfWeek, 'today' => $date->isSameDay(now($timezone))];
        }
        $groupOptions = [];
        foreach ([...$groups->activeGroupsForScheduling($organizationId), ...array_values($groupRecords)] as $group) {
            $groupOptions[$group->id] = ['id' => $group->id, 'name' => LocalizedJsonColumn::display($group->name)];
        }
        $courseOptions = [];
        foreach ($catalog->programs($organizationId) as $program) {
            foreach ($catalog->courses($organizationId, $program->id) as $course) {
                $courseOptions[$course->id] = ['id' => $course->id, 'name' => LocalizedJsonColumn::display($course->name)];
            }
        }
        $teacherOptions = [];
        foreach ($staff->activeTeacherSummariesForOrganization($organizationId) as $teacher) {
            $teacherOptions[$teacher['staff_profile_id']] = ['id' => $teacher['staff_profile_id'], 'name' => $teacher['name']];
        }
        foreach ($teacherNames as $id => $name) {
            $teacherOptions[$id] ??= ['id' => $id, 'name' => $name];
        }
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) config('console.report_per_page');

        return Inertia::render('Console/Sessions', [
            'filters' => $filters, 'school' => $school, 'timezones' => timezone_identifiers_list(),
            'from' => $from->toDateString(), 'until' => $until->subDay()->toDateString(),
            'previous' => ($filters['view'] === 'day' ? $anchor->subDay() : $anchor->subWeek())->toDateString(),
            'next' => ($filters['view'] === 'day' ? $anchor->addDay() : $anchor->addWeek())->toDateString(),
            'today' => now($timezone)->toDateString(), 'days' => $days, 'sessions' => $rows, 'limited' => $limited,
            'list' => (new LengthAwarePaginator(array_slice($rows, ($page - 1) * $perPage, $perPage), count($rows), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]))->toArray(),
            'schedules' => $scheduleRows, 'groups' => array_values($groupOptions), 'courses' => array_values($courseOptions), 'teachers' => array_values($teacherOptions),
            'statuses' => array_map(static fn (SessionStatus $status): array => ['id' => $status->value, 'name' => $status->label()], SessionStatus::cases()),
            'can' => [
                'manage' => $user?->can('schedule.manage') ?? false, 'schedules' => $user?->can('schedule.view') ?? false,
                'group' => $user?->can('group.view') ?? false, 'teacher' => $user?->can('staff.view.any') ?? false,
                'quran' => $user?->can('student.view.any') ?? false,
            ],
        ]);
    }
}
