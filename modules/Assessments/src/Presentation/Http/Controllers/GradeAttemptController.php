<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Assessments\Application\Actions\GradeAttemptAction;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Presentation\Http\Requests\GradeAttemptRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentAttemptResource;

/**
 * تصحيح محاولة وإعلان النتيجة.
 */
final class GradeAttemptController extends Controller
{
    public function __construct(
        private readonly GradeAttemptAction $action,
    ) {}

    public function __invoke(GradeAttemptRequest $request, string $attempt): AssessmentAttemptResource
    {
        $attemptModel = AssessmentAttempt::query()->findOrFail($attempt);

        $graded = $this->action->execute(
            $attemptModel,
            (int) $request->validated('score'),
        );

        return new AssessmentAttemptResource($graded);
    }
}
