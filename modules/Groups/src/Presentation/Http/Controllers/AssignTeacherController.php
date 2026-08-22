<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Requests\AssignTeacherRequest;
use Modules\Groups\Presentation\Http\Resources\GroupTeacherResource;

/**
 * إسناد معلم إلى مجموعة.
 */
final class AssignTeacherController extends Controller
{
    public function __construct(
        private readonly AssignTeacherAction $action,
    ) {}

    public function __invoke(AssignTeacherRequest $request, Group $group): JsonResponse
    {
        $assignment = $this->action->execute($group, $request->validated());

        return GroupTeacherResource::make($assignment)
            ->response()
            ->setStatusCode(201);
    }
}
