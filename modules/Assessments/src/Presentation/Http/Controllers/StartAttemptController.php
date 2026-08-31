<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\StartAttemptAction;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Requests\StartAttemptRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentAttemptResource;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;

/**
 * بدء محاولة اختبار لطالب.
 */
final class StartAttemptController extends Controller
{
    public function __construct(
        private readonly StartAttemptAction $action,
        private readonly StudentDirectoryQueries $students,
    ) {}

    public function __invoke(StartAttemptRequest $request, string $assessment): AssessmentAttemptResource
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('view', $assessmentModel);

        $user = $request->user();
        $student = $this->students->forUserIds(
            (string) $user?->organization_id,
            [(string) $user?->getAuthIdentifier()],
        )[0] ?? null;

        if ($student === null || $student->archived) {
            throw BusinessRuleViolation::make(
                'assessments.student_profile_required',
                'assessments::errors.student_profile_required',
            );
        }

        $attempt = $this->action->execute(
            $assessmentModel,
            $student->id,
            (string) $user?->getAuthIdentifier(),
        );

        return new AssessmentAttemptResource($attempt);
    }
}
