<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;

/**
 * قائمة الأنشطة — محصورة بنطاق مؤسسة المستخدم.
 */
final class ListAssignmentsController extends Controller
{
    public function __construct(
        private readonly AssignmentAudienceQueries $audiences,
    ) {}

    public function __invoke(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Assignment::class);

        $user = Auth::user();
        $organizationId = (string) $user?->organization_id;
        $audience = $this->audiences->forUser(
            $organizationId,
            (string) $user?->getAuthIdentifier(),
        );

        $query = Assignment::query()->forOrganization($organizationId);
        $administrativeView = $user?->can('assignment.manage') === true
            && ($user->can('settings.manage')
                || $user->can('student.update')
                || $user->can('message.moderate'));

        if ($administrativeView) {
            $query->withCount('submissions');
        } elseif (($user?->can('assignment.manage') === true || $user?->can('assignment.grade') === true)
            && $audience->staffProfileId !== null) {
            $query->where('staff_profile_id', $audience->staffProfileId)
                ->withCount('submissions');
        } elseif ($user?->can('assignment.submit') === true && $audience->studentProfileId !== null) {
            $query->where(function ($target) use ($audience): void {
                $target->where(function ($groupTarget) use ($audience): void {
                    $groupTarget->whereNotNull('group_id')
                        ->whereIn('group_id', $audience->activeGroupIds);
                })->orWhere(function ($courseTarget) use ($audience): void {
                    $courseTarget->whereNull('group_id')
                        ->whereIn('course_id', $audience->activeCourseIds);
                });
            });
        } else {
            abort(403);
        }

        $assignments = $query->orderBy('due_at')->paginate();

        return AssignmentResource::collection($assignments);
    }
}
