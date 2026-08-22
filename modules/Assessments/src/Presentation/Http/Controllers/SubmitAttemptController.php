<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Assessments\Application\Actions\SubmitAttemptAction;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Presentation\Http\Requests\SubmitAttemptRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentAttemptResource;

/**
 * تسليم محاولة اختبار.
 */
final class SubmitAttemptController extends Controller
{
    public function __construct(
        private readonly SubmitAttemptAction $action,
    ) {}

    public function __invoke(SubmitAttemptRequest $request, string $attempt): AssessmentAttemptResource
    {
        $attemptModel = AssessmentAttempt::query()->findOrFail($attempt);

        $updated = $this->action->execute(
            $attemptModel,
            (array) $request->validated('answers'),
        );

        return new AssessmentAttemptResource($updated);
    }
}
