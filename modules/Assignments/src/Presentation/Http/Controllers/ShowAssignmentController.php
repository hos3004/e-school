<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;

/**
 * عرض نشاط واحد.
 */
final class ShowAssignmentController extends Controller
{
    public function __invoke(Assignment $assignment): AssignmentResource
    {
        Gate::authorize('view', $assignment);

        return new AssignmentResource($assignment->load('submissions'));
    }
}
