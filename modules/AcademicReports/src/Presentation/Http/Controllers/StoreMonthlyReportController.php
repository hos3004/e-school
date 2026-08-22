<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Presentation\Http\Requests\StoreMonthlyReportRequest;
use Modules\AcademicReports\Presentation\Http\Resources\MonthlyReportResource;

/**
 * توليد تقرير شهري مسوّدة.
 */
final class StoreMonthlyReportController extends Controller
{
    public function __construct(
        private readonly DraftMonthlyReportAction $action,
    ) {}

    public function __invoke(StoreMonthlyReportRequest $request): MonthlyReportResource
    {
        $validated = $request->validated();

        $report = $this->action->execute(
            organizationId: (string) $validated['organization_id'],
            studentProfileId: (string) $validated['student_profile_id'],
            enrollmentId: (string) $validated['enrollment_id'],
            periodYear: (int) $validated['period_year'],
            periodMonth: (int) $validated['period_month'],
            metrics: $validated['metrics'] ?? [],
            supervisorSummary: $validated['supervisor_summary'] ?? null,
        );

        return (new MonthlyReportResource($report))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
