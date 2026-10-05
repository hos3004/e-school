<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Console\Support\ConsoleReportPresentation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ReadOperationalReportRequest;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\Exceptions\InvalidReportCriteria;
use Modules\Sessions\Domain\Enums\SessionStatus;

final class ReportsController extends Controller
{
    public function __invoke(ReadOperationalReportRequest $request, OperationalReportCriteriaFactory $factory, OperationalReportQuery $reports, ConsoleReportPresentation $presentation): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);
        try {
            $criteria = $factory->fromInput($request->validated(), $user);
        } catch (InvalidReportCriteria $error) {
            throw ValidationException::withMessages(['period' => $error->getMessage()])
                ->redirectTo(route('console.reports'));
        }
        $report = $reports->run($criteria);
        $perPage = max(1, (int) config('console.report_per_page'));
        $count = count($report->rows);
        $lastPage = max(1, (int) ceil($count / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);
        $parameters = $criteria->toQueryParameters();
        $options = $reports->options($criteria);
        foreach ($reports->selectedOptions($criteria) as $type => $selected) {
            $options[$type] = [...($options[$type] ?? []), ...$selected];
        }

        return Inertia::render('Console/Reports', [
            'summary' => $report->summary,
            'overview' => $presentation->summarize($report),
            'rows' => array_slice($report->rowsAsArray(), ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'current' => $page, 'last' => $lastPage, 'total' => $count,
                'previousUrl' => $page > 1 ? route('console.reports', [...$parameters, 'page' => $page - 1]) : null,
                'nextUrl' => $page < $lastPage ? route('console.reports', [...$parameters, 'page' => $page + 1]) : null,
            ],
            'filters' => $parameters,
            'options' => $options,
            'filterChoices' => [
                'statuses' => array_map(static fn (SessionStatus $status): array => ['value' => $status->value, 'label' => $status->label()], SessionStatus::cases()),
                'attendance_statuses' => array_map(static fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->label()], AttendanceStatus::cases()),
                'session_types' => array_map(static fn (string $type): array => ['value' => $type, 'label' => __('sessions::session_types.'.$type)], array_keys((array) config('academic.session_types'))),
            ],
            'timezone' => $criteria->timezone,
            'limitExceeded' => $report->limitExceeded,
            'exportUrl' => $user->can('report.export')
                ? route('console.reports.pdf', $parameters)
                : null,
        ]);
    }
}
