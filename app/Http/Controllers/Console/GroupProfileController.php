<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\MessagingChannelOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Application\Services\ConsoleSetupService;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Scheduling\Application\Queries\GroupPlacementScheduleQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\LocalizedJsonColumn;

final class GroupProfileController extends Controller
{
    public function __invoke(Request $request, string $group, ConsoleSetupService $setup, GroupAdministrationQueries $groups,
        AcademicCatalogQueries $catalog, StudentDirectoryQueries $students, StaffQueries $staff, GroupPlacementScheduleQueries $schedules,
        OperationalReportQuery $reports, OperationalReportCriteriaFactory $criteria, ConsoleContext $context): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $organizationId = (string) data_get($user, 'organization_id');
        $record = collect($setup->groups($organizationId))->firstWhere('id', $group);
        abort_if($record === null, 404);
        $programs = $catalog->programsByIds($organizationId, $record['program_ids']);
        $members = $user->can('student.view.any') ? $groups->membershipsForGroup($organizationId, $group) : [];
        $names = $students->namesForProfiles($organizationId, array_values(array_unique(array_column($members, 'studentProfileId'))));
        $studentRecords = $students->byIds($organizationId, array_values(array_unique(array_column($members, 'studentProfileId'))));
        $assignments = $groups->assignmentsForGroup($organizationId, $group);
        $scheduleRows = $schedules->forGroup($organizationId, $group);
        $courseIds = array_values(array_unique(array_filter([...array_column($assignments, 'courseId'), ...array_column($scheduleRows, 'course_id')])));
        $courses = $catalog->coursesByIds($organizationId, $courseIds);
        $teacherNames = $staff->namesForProfiles($organizationId, array_values(array_unique([...array_column($assignments, 'staffProfileId'), ...array_column($scheduleRows, 'staff_profile_id')])));
        $report = $user->can('report.view') ? $reports->run($criteria->fromInput(['preset' => 'this_month', 'group_id' => $group], $user)) : null;

        return Inertia::render('Console/GroupProfile', [
            'group' => $record,
            'programs' => array_values(array_map(static fn ($item): array => ['id' => $item->id, 'label' => LocalizedJsonColumn::display($item->name)], $programs)),
            'courses' => array_map(static fn ($item): string => LocalizedJsonColumn::display($item->name), $courses),
            'members' => array_map(static fn ($member): array => [
                'id' => $member->membershipId, 'studentId' => $member->studentProfileId,
                'name' => $names[$member->studentProfileId] ?? __('console.not_set'),
                'code' => $studentRecords[$member->studentProfileId]->studentCode ?? '',
                'status' => $member->status, 'statusLabel' => MembershipStatus::tryFrom($member->status)?->label() ?? $member->status,
                'joinedAt' => $member->joinedAt, 'leftAt' => $member->leftAt,
            ], $members),
            'teachers' => array_map(static fn ($assignment): array => [
                'id' => $assignment->assignmentId, 'staffId' => $assignment->staffProfileId,
                'name' => $teacherNames[$assignment->staffProfileId] ?? __('console.unassigned'),
                'courseId' => $assignment->courseId, 'role' => __('console_courses.options.'.$assignment->role),
                'from' => $assignment->assignedFrom, 'until' => $assignment->assignedTo,
            ], $assignments),
            'schedules' => array_map(static fn (array $schedule): array => [...$schedule, 'teacher' => $teacherNames[$schedule['staff_profile_id']] ?? __('console.unassigned')], $scheduleRows),
            'sessions' => array_slice($report?->rowsAsArray() ?? [], 0, (int) config('console.report_per_page')),
            'summary' => $report?->summary,
            'limitExceeded' => $report !== null && $report->limitExceeded,
            'timezone' => $context->forRequest($request)['timezone'],
            'abilities' => ['edit' => $user->can('group.manage'), 'students' => $user->can('student.view.any'),
                'teachers' => $user->can('staff.view.any'), 'placement' => $user->can('student.view.any') && $user->can('enrollment.create') && $user->can('group.manage'),
                'schedule' => $user->can('schedule.manage'), 'report' => $user->can('report.view')],
            'messaging' => $this->messaging($request, $group, LocalizedJsonColumn::display($record['name'] ?? [])),
        ]);
    }

    /**
     * زر مراسلة أطراف المجموعة.
     *
     * الهدف مثبّت على هذه المجموعة، والجمهور يختاره المرسل: الطلاب فقط أو
     * المعلم فقط أو الجميع. الإرسال رسائل فردية منفصلة في كل الحالات.
     *
     * @return array<string, mixed>|null
     */
    private function messaging(Request $request, string $groupId, string $label): ?array
    {
        if ($request->user()?->can('notifications.outbox.create') !== true) {
            return null;
        }

        $options = app(MessagingChannelOptions::class);
        $channels = $options->all();

        return [
            'sendUrl' => route('console.messages.audience'),
            'targetsUrl' => route('console.messages.targets'),
            'templatesUrl' => route('console.messages.templates'),
            'channels' => $channels,
            'defaultChannel' => $options->defaultFor($channels),
            'fixedTarget' => ['type' => 'group', 'id' => $groupId, 'label' => $label],
        ];
    }
}
