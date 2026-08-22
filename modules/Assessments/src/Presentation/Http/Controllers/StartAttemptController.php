<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\StartAttemptAction;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Requests\StartAttemptRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentAttemptResource;

/**
 * بدء محاولة اختبار لطالب.
 */
final class StartAttemptController extends Controller
{
    public function __construct(
        private readonly StartAttemptAction $action,
    ) {}

    public function __invoke(StartAttemptRequest $request, string $assessment): AssessmentAttemptResource
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('view', $assessmentModel);

        $attempt = $this->action->execute(
            $assessmentModel,
            (string) $request->validated('student_profile_id'),
        );

        return new AssessmentAttemptResource($attempt);
    }
}
