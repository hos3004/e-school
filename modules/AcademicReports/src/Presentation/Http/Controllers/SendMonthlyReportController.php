<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Application\Actions\SendMonthlyReportAction;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\AcademicReports\Presentation\Http\Requests\SendMonthlyReportRequest;
use Modules\AcademicReports\Presentation\Http\Resources\MonthlyReportResource;

/**
 * إرسال التقرير الشهري للطالب ووليّ الأمر.
 */
final class SendMonthlyReportController extends Controller
{
    public function __construct(
        private readonly SendMonthlyReportAction $action,
    ) {}

    public function __invoke(SendMonthlyReportRequest $request, string $report): MonthlyReportResource
    {
        $reportModel = MonthlyReport::query()->findOrFail($report);

        Gate::authorize('send', $reportModel);

        $this->action->execute($reportModel);

        return new MonthlyReportResource($reportModel->refresh());
    }
}
