<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\TeacherDuesReadRequest;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Payroll\Domain\Contracts\TeacherDuesQueries;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportRow;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Staff\Domain\Contracts\StaffQueries;

/** Composition of public DTOs only; all monetary totals remain owned by Payroll. */
final class TeacherDuesController extends Controller
{
    public function __invoke(TeacherDuesReadRequest $request, TeacherDuesQueries $dues, StaffQueries $staff,
        OperationalReportQuery $reports, UserAccountDirectory $accounts, ConsoleContext $context): Response
    {
        abort_unless((bool) config('features.payroll'), 404);
        $actor = $request->user();
        $org = (string) $actor->getAttribute('organization_id');
        abort_if($org === '', 403);
        $filters = $request->validated();
        $periods = $dues->periods($actor);
        $periodId = $filters['period'] ?? ($periods[0]['id'] ?? null);
        $statement = $periodId === null ? null : $dues->statement($actor, $periodId);
        $staffIds = $staff->profileIdsForOrganization($org);
        $historicalIds = $statement === null ? [] : array_column($statement->teachers, 'staffProfileId');
        $readableIds = array_unique([...$staffIds, ...$historicalIds]);
        $names = $staff->namesForProfiles($org, $staffIds);
        asort($names);
        foreach (['teacher', 'staff_profile_id'] as $field) {
            if (!empty($filters[$field])) {
                abort_unless(in_array($filters[$field], $readableIds, true), 404);
            }
        }
        abort_if(!empty($filters['teacher']) && !empty($filters['staff_profile_id']) && $filters['teacher'] !== $filters['staff_profile_id'], 404);
        $zone = (string) $context->forRequest($request)['timezone'];
        $timezone = in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
        $base = ['periods' => $periods, 'teacherOptions' => $names, 'timezone' => $timezone,
            'currency' => (string) config('payroll.currency'), 'filters' => [...$filters, 'period' => $periodId],
            'canPropose' => (bool) $actor->can((string) config('payroll.adjustments.propose_permission')),
            'adjustmentTypes' => array_values((array) config('payroll.adjustments.types'))];
        if ($statement === null) {
            return Inertia::render('Console/TeacherDues', [...$base, 'period' => null, 'teachers' => [],
                'detail' => null, 'limitExceeded' => false, 'pagination' => null]);
        }
        $period = $statement->period;
        $criteria = new OperationalReportCriteria(organizationId: $org,
            fromUtc: CarbonImmutable::parse($period['startsOn'], 'UTC')->startOfDay(),
            untilUtcExclusive: CarbonImmutable::parse($period['endsOn'], 'UTC')->addDay()->startOfDay(),
            timezone: $timezone, preset: 'custom', fromDate: $period['startsOn'], untilDate: $period['endsOn'],
            staffProfileId: $filters['staff_profile_id'] ?? null);
        $report = $reports->run($criteria);
        $rowsByTeacher = [];
        $rowsById = [];
        foreach ($report->rows as $row) {
            $rowsByTeacher[$row->actualTeacherId][] = $row;
            $rowsById[$row->id] = $row;
        }
        $moneyByTeacher = array_column($statement->teachers, 'totals', 'staffProfileId');
        $candidateIds = array_values(array_unique([...array_keys($rowsByTeacher), ...array_keys($moneyByTeacher),
            ...array_filter([$filters['staff_profile_id'] ?? null, $filters['teacher'] ?? null])]));
        $track = $filters['track'] ?? 'all';
        $search = mb_strtolower(trim($filters['search'] ?? ''));
        $teachers = [];
        foreach ($candidateIds as $id) {
            if (!empty($filters['staff_profile_id']) && $id !== $filters['staff_profile_id']) {
                continue;
            }
            $name = $names[$id] ?? __('console_dues.archived_teacher');
            if ($search !== '' && !str_contains(mb_strtolower($name), $search)) {
                continue;
            }
            $rows = $rowsByTeacher[$id] ?? [];
            if ($track !== 'all' && !array_any($rows, static fn (OperationalReportRow $row): bool => ($row->groupId === '' ? 'individual' : 'group') === $track)) {
                continue;
            }
            $teachers[] = ['id' => $id, 'name' => $name, 'counts' => $report->limitExceeded ? null : $this->counts($rows),
                'totals' => $moneyByTeacher[$id] ?? [],
                'detailUrl' => route('console.teacher-dues.index', [...$filters, 'period' => $periodId, 'teacher' => $id]),
            ];
        }
        usort($teachers, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $detail = null;
        if (!empty($filters['teacher'])) {
            $id = $filters['teacher'];
            $entries = array_values(array_filter($statement->entries, static fn (array $entry): bool => $entry['staffProfileId'] === $id));
            $adjustments = array_values(array_filter($statement->adjustments, static fn (array $item): bool => $item['staffProfileId'] === $id));
            $people = $accounts->findMany($org, array_values(array_unique(array_filter(array_merge(
                array_column($adjustments, 'proposedBy'), array_column($adjustments, 'approvedBy'))))));
            $entriesBySession = [];
            foreach ($entries as $entry) {
                if ($entry['sessionId'] !== null) {
                    $entriesBySession[$entry['sessionId']][] = $entry;
                }
            }
            $detailLimitExceeded = $report->limitExceeded;
            // A filtered actual-teacher report omits substitutions charged to the original teacher.
            // Merge those rows for explanation only; delivered counts keep using the actual teacher.
            if (array_diff(array_keys($entriesBySession), array_keys($rowsById)) !== []) {
                $originalReport = $reports->run(new OperationalReportCriteria(
                    organizationId: $org, fromUtc: $criteria->fromUtc, untilUtcExclusive: $criteria->untilUtcExclusive,
                    timezone: $timezone, preset: 'custom', fromDate: $period['startsOn'], untilDate: $period['endsOn'],
                    originalStaffProfileId: $id,
                ));
                $detailLimitExceeded = $detailLimitExceeded || $originalReport->limitExceeded;
                foreach ($originalReport->rows as $row) {
                    if (isset($entriesBySession[$row->id])) {
                        $rowsById[$row->id] = $row;
                    }
                }
            }
            $lessons = [];
            foreach ($rowsById as $row) {
                if ($row->actualTeacherId !== $id && !isset($entriesBySession[$row->id])) {
                    continue;
                }
                $lessons[] = ['id' => $row->id, 'title' => $row->title, 'startsAt' => $row->scheduledStart,
                    'course' => $row->course, 'study' => $row->group !== '' ? $row->group : ($actor->can('student.view.any') ? $row->studentsDisplay : __('console_dues.individual')),
                    'track' => $row->groupId === '' ? 'individual' : 'group', 'studentCount' => count($row->students),
                    'durationMinutes' => $row->actualDurationMinutes ?? $row->durationMinutes,
                    'actualDuration' => $row->actualDurationMinutes !== null, 'status' => $row->status,
                    'statusLabel' => $row->statusLabel, 'approved' => $row->status === SessionStatus::Completed->value,
                    'awaitingReview' => $row->status === SessionStatus::AwaitingReview->value,
                    'actualTeacher' => $row->actualTeacher, 'isActualTeacher' => $row->actualTeacherId === $id,
                    'entries' => $entriesBySession[$row->id] ?? []];
            }
            usort($lessons, static fn (array $a, array $b): int => strcmp($a['startsAt'], $b['startsAt']));
            $detail = ['limitExceeded' => $detailLimitExceeded, 'canPropose' => $staff->userIdForProfile($org, $id) !== null, 'id' => $id, 'name' => $names[$id] ?? __('console_dues.archived_teacher'),
                'counts' => $report->limitExceeded ? null : $this->counts($rowsByTeacher[$id] ?? []),
                'totals' => $moneyByTeacher[$id] ?? [], 'lessons' => $lessons, 'entries' => $entries,
                'adjustments' => array_map(static fn (array $item): array => [...$item,
                    'proposedByName' => $people[$item['proposedBy']]->name ?? __('console_dues.unavailable'),
                    'approvedByName' => $people[$item['approvedBy'] ?? '']->name ?? null,
                    'approveUrl' => $item['canApprove'] ? route('console.teacher-dues.approve', ['adjustment' => $item['id']]) : null,
                    'rejectUrl' => $item['canReject'] ? route('console.teacher-dues.reject', ['adjustment' => $item['id']]) : null,
                ], $adjustments),
                'proposeUrl' => route('console.teacher-dues.propose', ['period' => $periodId]),
                'profileUrl' => in_array($id, $staffIds, true) && $actor->can('staff.view.any') ? route('console.teachers.show', ['profile' => $id]) : null,
                'closeUrl' => route('console.teacher-dues.index', array_diff_key([...$filters, 'period' => $periodId], ['teacher' => true]))];
        }
        $perPage = max(1, (int) config('console.report_per_page', 25));
        $count = count($teachers);
        $last = max(1, (int) ceil($count / $perPage));
        $page = min($last, $request->integer('page', 1));

        return Inertia::render('Console/TeacherDues', [...$base, 'period' => $period,
            'teachers' => array_slice($teachers, ($page - 1) * $perPage, $perPage), 'detail' => $detail,
            'limitExceeded' => $report->limitExceeded,
            'pagination' => ['current' => $page, 'last' => $last, 'total' => $count,
                'previousUrl' => $page > 1 ? route('console.teacher-dues.index', [...$filters, 'period' => $periodId, 'page' => $page - 1]) : null,
                'nextUrl' => $page < $last ? route('console.teacher-dues.index', [...$filters, 'period' => $periodId, 'page' => $page + 1]) : null],
        ]);
    }

    /** @param list<OperationalReportRow> $rows
     * @return array{total: int, delivered: int, approved: int, pending: int, cancelled: int, minutes: int}
     */
    private function counts(array $rows): array
    {
        $approved = 0;
        $pending = 0;
        $cancelled = 0;
        $minutes = 0;
        foreach ($rows as $row) {
            $completed = $row->status === SessionStatus::Completed->value;
            $waiting = $row->status === SessionStatus::AwaitingReview->value;
            $approved += (int) $completed;
            $pending += (int) $waiting;
            if ($completed || $waiting) {
                $minutes += $row->actualDurationMinutes ?? $row->durationMinutes;
            }
            $cancelled += (int) in_array($row->status, [SessionStatus::CancelledByStudent->value, SessionStatus::CancelledByTeacher->value, SessionStatus::CancelledBySchool->value], true);
        }

        return ['total' => count($rows), 'delivered' => $approved + $pending, 'approved' => $approved,
            'pending' => $pending, 'cancelled' => $cancelled, 'minutes' => $minutes];
    }
}
