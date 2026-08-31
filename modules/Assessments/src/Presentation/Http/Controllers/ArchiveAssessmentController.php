<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\ArchiveAssessmentAction;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Requests\ArchiveAssessmentRequest;

/**
 * أرشفة اختبار.
 */
final class ArchiveAssessmentController extends Controller
{
    public function __construct(
        private readonly ArchiveAssessmentAction $action,
    ) {}

    public function __invoke(ArchiveAssessmentRequest $request, string $assessment): JsonResponse
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('delete', $assessmentModel);

        $this->action->execute(
            $assessmentModel,
            (string) $request->user()?->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return response()->json(status: 204);
    }
}
