<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;

/**
 * عرض نشاط واحد.
 */
final class ShowAssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentAudienceQueries $audiences,
    ) {}

    public function __invoke(Assignment $assignment): AssignmentResource
    {
        Gate::authorize('view', $assignment);

        $user = request()->user();
        $audience = $this->audiences->forUser(
            (string) $assignment->organization_id,
            (string) $user?->getAuthIdentifier(),
        );

        if ($user?->can('assignment.grade') === true || $user?->can('assignment.manage') === true) {
            $assignment->load('submissions');
        } elseif ($audience->studentProfileId !== null) {
            $studentProfileId = $audience->studentProfileId;
            $assignment->load([
                'submissions' => static fn ($query) => $query->where('student_profile_id', $studentProfileId),
            ]);
        }

        return new AssignmentResource($assignment);
    }
}
