<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AcademicReports\Application\Actions\DraftMonthlyReportAction;
use Modules\AcademicReports\Application\Services\MonthlyReportScopeValidator;
use Modules\AcademicReports\Presentation\Http\Requests\StoreMonthlyReportRequest;
use Modules\AcademicReports\Presentation\Http\Resources\MonthlyReportResource;

/**
 * توليد تقرير شهري مسوّدة.
 */
final class StoreMonthlyReportController extends Controller
{
    public function __construct(
        private readonly DraftMonthlyReportAction $action,
        private readonly MonthlyReportScopeValidator $scope,
    ) {}

    public function __invoke(StoreMonthlyReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $organizationId = (string) $request->user()->organization_id;
        $studentProfileId = (string) $validated['student_profile_id'];
        $enrollmentId = (string) $validated['enrollment_id'];

        $this->scope->validate($organizationId, $studentProfileId, $enrollmentId);

        $report = $this->action->execute(
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            enrollmentId: $enrollmentId,
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
