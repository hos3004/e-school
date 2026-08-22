<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\EnrollStudentAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Requests\EnrollStudentRequest;
use Modules\Groups\Presentation\Http\Resources\GroupMembershipResource;

/**
 * تسجيل طالب في مجموعة.
 */
final class EnrollStudentController extends Controller
{
    public function __construct(
        private readonly EnrollStudentAction $action,
    ) {}

    public function __invoke(EnrollStudentRequest $request, Group $group): JsonResponse
    {
        $membership = $this->action->execute(
            $group,
            (string) $request->validated('student_profile_id'),
        );

        return GroupMembershipResource::make($membership)
            ->response()
            ->setStatusCode(201);
    }
}
