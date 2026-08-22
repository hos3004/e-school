<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;

/**
 * قائمة الأنشطة — محصورة بنطاق مؤسسة المستخدم.
 */
final class ListAssignmentsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Assignment::class);

        $organizationId = (string) Auth::user()?->organization_id;

        $assignments = Assignment::query()
            ->forOrganization($organizationId)
            ->withCount('submissions')
            ->orderBy('due_at')
            ->paginate();

        return AssignmentResource::collection($assignments);
    }
}
