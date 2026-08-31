<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\UpdateAssessmentAction;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Requests\UpdateAssessmentRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentResource;

/**
 * تعديل اختبار قائم.
 */
final class UpdateAssessmentController extends Controller
{
    public function __construct(
        private readonly UpdateAssessmentAction $action,
    ) {}

    public function __invoke(UpdateAssessmentRequest $request, string $assessment): AssessmentResource
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('update', $assessmentModel);

        // السبب يخص قيد التدقيق وحده — لا يُمرَّر كسمة على نموذج الاختبار.
        $this->action->execute(
            $assessmentModel,
            $request->safe()->except('reason'),
            (string) $request->user()?->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return new AssessmentResource($assessmentModel->refresh());
    }
}
