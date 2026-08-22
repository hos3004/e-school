<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Presentation\Http\Resources\SessionReportResource;

/**
 * عرض تقرير حصة واحد.
 */
final class ShowSessionReportController extends Controller
{
    public function __invoke(string $report): SessionReportResource
    {
        $reportModel = SessionReport::query()->with('students')->findOrFail($report);

        Gate::authorize('view', $reportModel);

        return new SessionReportResource($reportModel);
    }
}
