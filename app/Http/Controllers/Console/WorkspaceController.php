<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleContext;
use App\Http\Controllers\Console\Support\ConsoleReportPresentation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Groups\Application\Services\ConsoleSetupService as GroupSetup;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Students\Application\Services\ConsoleRegistrationService;

final class WorkspaceController extends Controller
{
    public function __invoke(Request $request, OperationalReportCriteriaFactory $criteria, OperationalReportQuery $reports,
        ConsoleReportPresentation $presentation, ConsoleContext $context, GroupSetup $groups, ConsoleRegistrationService $registration): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $report = $user->can('report.view') ? $reports->run($criteria->fromInput(['preset' => 'today'], $user)) : null;
        $week = $user->can('report.view') ? $reports->run($criteria->fromInput(['preset' => 'this_week'], $user)) : null;
        $organizationId = (string) data_get($user, 'organization_id');
        $timezone = (string) $context->forRequest($request)['timezone'];
        $allGroups = $user->can('group.view') ? $groups->groups($organizationId) : [];
        $preparation = array_values(array_filter($allGroups, static fn (array $group): bool => $group['status'] === 'planning'));

        return Inertia::render('Console/Workspace', [
            'summary' => $report?->summary,
            'sessions' => $report?->rowsAsArray() ?? [],
            'overview' => $report === null ? null : $presentation->summarize($report),
            'week' => $week === null ? [] : $presentation->summarize($week)['days'],
            'recent' => array_slice(array_reverse(array_values(array_filter($week?->rowsAsArray() ?? [], static fn (array $row): bool => $row['status'] === 'completed'))), 0, 4),
            'preparation' => array_slice($preparation, 0, 4),
            'preparationCount' => count($preparation),
            'registration' => $user->can('student.create') ? $registration->counts($organizationId) : null,
            'timezone' => $timezone,
            'canSchedule' => $user->can('schedule.manage'),
            'date' => now($timezone)->format('Y-m-d'),
            'limitExceeded' => ($report !== null && $report->limitExceeded) || ($week !== null && $week->limitExceeded),
        ]);
    }
}
