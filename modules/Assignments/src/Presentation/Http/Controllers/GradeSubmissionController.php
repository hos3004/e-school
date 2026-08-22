<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Assignments\Application\Actions\GradeSubmissionAction;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Assignments\Presentation\Http\Requests\GradeSubmissionRequest;
use Modules\Assignments\Presentation\Http\Resources\AssignmentSubmissionResource;

/**
 * رصد درجة تسليم.
 */
final class GradeSubmissionController extends Controller
{
    public function __construct(
        private readonly GradeSubmissionAction $action,
    ) {}

    public function __invoke(GradeSubmissionRequest $request, AssignmentSubmission $submission): AssignmentSubmissionResource
    {
        return new AssignmentSubmissionResource($this->action->execute($submission, $request->validated()));
    }
}
