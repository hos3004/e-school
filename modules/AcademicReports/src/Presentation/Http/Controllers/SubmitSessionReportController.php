<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\AcademicReports\Presentation\Http\Requests\SubmitSessionReportRequest;
use Modules\AcademicReports\Presentation\Http\Resources\SessionReportResource;

/**
 * تقديم تقرير الحصة.
 */
final class SubmitSessionReportController extends Controller
{
    public function __construct(
        private readonly SubmitSessionReportAction $action,
    ) {}

    public function __invoke(SubmitSessionReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $report = $this->action->execute(
            sessionId: (string) $validated['session_id'],
            staffProfileId: (string) $validated['staff_profile_id'],
            students: $validated['students'],
            sessionEndedAt: isset($validated['session_ended_at'])
                ? CarbonImmutable::parse($validated['session_ended_at'], 'UTC')
                : null,
            topicsCovered: $validated['topics_covered'] ?? null,
            homeworkAssigned: $validated['homework_assigned'] ?? null,
            generalNotes: $validated['general_notes'] ?? null,
            supervisorPrivateNote: $validated['supervisor_private_note'] ?? null,
            nextSessionPlan: $validated['next_session_plan'] ?? null,
        );

        return (new SessionReportResource($report->load('students')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
