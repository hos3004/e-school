<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\AcademicReports\Application\Actions\ApproveMonthlyReportAction;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\AcademicReports\Presentation\Http\Requests\ApproveMonthlyReportRequest;
use Modules\AcademicReports\Presentation\Http\Resources\MonthlyReportResource;

/**
 * اعتماد التقرير الشهري.
 */
final class ApproveMonthlyReportController extends Controller
{
    public function __construct(
        private readonly ApproveMonthlyReportAction $action,
    ) {}

    public function __invoke(ApproveMonthlyReportRequest $request, string $report): MonthlyReportResource
    {
        $reportModel = MonthlyReport::query()->findOrFail($report);

        $this->action->execute(
            report: $reportModel,
            approverId: (string) auth()->id(),
            reason: (string) $request->validated('reason'),
        );

        return new MonthlyReportResource($reportModel->refresh());
    }
}
