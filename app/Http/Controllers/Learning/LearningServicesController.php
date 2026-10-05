<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Domain\Contracts\TeacherEarningsQueries;
use Modules\Payroll\Domain\ValueObjects\TeacherPeriodEarnings;
use Modules\Scheduling\Application\Queries\ScheduleChangeQueries;

final readonly class LearningServicesController
{
    public function __construct(private PortalData $data, private ConsoleContext $context) {}

    /** @return array{string,string,string} */
    private function actor(Request $request, string $kind): array
    {
        abort_unless(in_array($kind, ['student', 'teacher'], true), 404);
        $org = (string) $request->user()?->getAttribute('organization_id');
        $user = (string) $request->user()?->getAuthIdentifier();
        $id = $kind === 'student' ? $this->data->studentProfileId($user, $org) : $this->data->staffProfileId($user, $org);
        abort_if($id === null, 403);

        return [$org, $id, (string) $this->context->forRequest($request)['timezone']];
    }

    public function schedule(Request $request, string $kind, ScheduleChangeQueries $scheduleChanges): Response
    {
        [$org, $id, $timezone] = $this->actor($request, $kind);
        abort_unless($request->user()?->can('schedule.view'), 403);
        $sessions = $kind === 'teacher' ? $this->data->teacherScheduleSessions($id, 'ar', $org) : $this->data->upcomingStudentSessions($id, 'ar', $org);
        $canRequestScheduleChange = $kind === 'teacher' && (bool) $request->user()->can('schedule.change.request');
        $permanentSchedules = $canRequestScheduleChange
            ? $scheduleChanges->teacherSchedules($org, $id, 'ar')
            : [];
        $scheduleChangeRequests = $kind === 'student' && $request->user()->can('schedule.change.respond')
            ? $scheduleChanges->studentPendingRequests($org, $id, 'ar')
            : [];

        return Inertia::render('Learning/Schedule', compact(
            'kind',
            'timezone',
            'sessions',
            'permanentSchedules',
            'scheduleChangeRequests',
            'canRequestScheduleChange',
        ));
    }

    public function notifications(Request $request, string $kind): Response
    {
        [, , $timezone] = $this->actor($request, $kind);

        return Inertia::render('Learning/NotificationsPage', compact('kind', 'timezone'));
    }

    public function reports(Request $request): Response
    {
        [$org, $id, $timezone] = $this->actor($request, 'student');
        abort_unless($request->user()?->can('session_report.view'), 403);

        return Inertia::render('Learning/Reports', ['reports' => $this->data->monthlyReports($id, 'ar', $org), 'timezone' => $timezone]);
    }

    public function earnings(Request $request, TeacherEarningsQueries $earnings): Response
    {
        [$org, $id, $timezone] = $this->actor($request, 'teacher');
        abort_unless((bool) config('features.payroll') && $request->user()?->can('payroll.view'), 403);

        return Inertia::render('Learning/Earnings', ['timezone' => $timezone, 'periods' => array_map(
            fn (TeacherPeriodEarnings $period): array => ['id' => $period->periodId, 'year' => $period->year, 'month' => $period->month,
                'status' => $period->status, 'currency' => $period->currency, 'earningsMinorUnits' => $period->earningsMinorUnits,
                'deductionsMinorUnits' => $period->deductionsMinorUnits, 'adjustmentsMinorUnits' => $period->adjustmentsMinorUnits,
                'netMinorUnits' => $period->netMinorUnits, 'sessionsCount' => $period->sessionsCount, 'entries' => array_map(fn (array $entry): array => [...$entry, 'sessionUrl' => !empty($entry['sessionId']) && $this->data->teacherSession($id, $entry['sessionId'], 'ar', $org) !== null ? route('learning.teacher.sessions.show', ['session' => $entry['sessionId']]) : null], $period->entries), 'adjustments' => $period->adjustments],
            $earnings->periodsFor($org, $id),
        )]);
    }
}
