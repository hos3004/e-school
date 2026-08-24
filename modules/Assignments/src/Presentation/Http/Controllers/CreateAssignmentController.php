<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Requests\CreateAssignmentRequest;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;

/**
 * إنشاء نشاط جديد.
 */
final class CreateAssignmentController extends Controller
{
    public function __construct(
        private readonly CreateAssignmentAction $action,
        private readonly AssignmentAudienceQueries $audiences,
    ) {}

    public function __invoke(CreateAssignmentRequest $request): AssignmentResource
    {
        Gate::authorize('create', Assignment::class);

        $user = $request->user();
        $organizationId = (string) $user->organization_id;
        $staffProfileId = $request->string('staff_profile_id')->toString();
        $administrativeOverride = $user->can('settings.manage')
            || $user->can('student.update')
            || $user->can('message.moderate');

        $allowedTarget = $administrativeOverride
            ? $this->audiences->staffProfileBelongsToOrganization($organizationId, $staffProfileId)
            : $this->audiences->teacherIsAssignedToTarget(
                organizationId: $organizationId,
                userId: (string) $user->getAuthIdentifier(),
                staffProfileId: $staffProfileId,
                courseId: $request->string('course_id')->toString(),
                groupId: $request->filled('group_id') ? $request->string('group_id')->toString() : null,
            );

        abort_unless($allowedTarget, 403);

        $data = array_merge($request->validated(), [
            'organization_id' => $organizationId,
        ]);

        return new AssignmentResource($this->action->execute($data));
    }
}
