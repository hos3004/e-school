<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Assignments\Application\Actions\SubmitAssignmentAction;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Assignments\Presentation\Http\Requests\SubmitAssignmentRequest;
use Modules\Assignments\Presentation\Http\Resources\AssignmentSubmissionResource;

/**
 * تسليم الطالب لنشاطه.
 */
final class SubmitAssignmentController extends Controller
{
    public function __construct(
        private readonly SubmitAssignmentAction $action,
    ) {}

    public function __invoke(SubmitAssignmentRequest $request, AssignmentSubmission $submission): AssignmentSubmissionResource
    {
        return new AssignmentSubmissionResource($this->action->execute($submission, $request->validated()));
    }
}
