<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\AcademicReports\Presentation\Http\Resources\MonthlyReportResource;

/**
 * عرض تقرير شهري واحد.
 */
final class ShowMonthlyReportController extends Controller
{
    public function __invoke(string $report): MonthlyReportResource
    {
        $reportModel = MonthlyReport::query()->findOrFail($report);

        Gate::authorize('view', $reportModel);

        return new MonthlyReportResource($reportModel);
    }
}
