<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\StartAttemptAction;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Presentation\Http\Requests\StartAttemptRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentAttemptResource;
use Modules\Discipline\Domain\Contracts\ReactivationRequestQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;

final class StartAttemptController extends Controller
{
    public function __construct(
        private readonly StartAttemptAction $action,
        private readonly StudentDirectoryQueries $students,
        private readonly ReactivationRequestQueries $reactivations,
    ) {}

    public function __invoke(StartAttemptRequest $request, string $assessment): AssessmentAttemptResource
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('create', [AssessmentAttempt::class, $assessmentModel]);

        $user = $request->user();
        $actorId = (string) $user?->getAuthIdentifier();
        $organizationId = (string) $user?->organization_id;
        $student = $this->students->forUserIds($organizationId, [$actorId])[0] ?? null;

        if ($student === null || $student->archived) {
            throw BusinessRuleViolation::make(
                'assessments.student_profile_required',
                'assessments::errors.student_profile_required',
            );
        }

        $reactivationRequestId = null;
        if ($assessmentModel->type === AssessmentType::Reactivation) {
            $candidateId = $request->validated('reactivation_request_id');

            if (!is_string($candidateId) || $candidateId === '') {
                throw BusinessRuleViolation::make(
                    'assessments.reactivation_request_required',
                    'assessments::errors.reactivation_request_required',
                );
            }

            $reactivation = $this->reactivations->find($candidateId);
            if ($reactivation === null) {
                throw BusinessRuleViolation::make(
                    'assessments.reactivation_request_not_found',
                    'assessments::errors.reactivation_request_not_found',
                );
            }

            if ($reactivation->organizationId !== $organizationId
                || $reactivation->requestedBy !== $actorId) {
                abort(403);
            }

            if (!$reactivation->canStartAssessment) {
                throw BusinessRuleViolation::make(
                    'assessments.reactivation_request_invalid_state',
                    'assessments::errors.reactivation_request_invalid_state',
                );
            }

            $reactivationRequestId = $reactivation->id;
        }

        $attempt = $this->action->execute(
            $assessmentModel,
            $student->id,
            $actorId,
            $reactivationRequestId,
        );

        return new AssessmentAttemptResource($attempt);
    }
}
