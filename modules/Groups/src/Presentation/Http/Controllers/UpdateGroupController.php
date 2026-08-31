<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\UpdateGroupAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Requests\UpdateGroupRequest;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * تعديل بيانات مجموعة قائمة.
 */
final class UpdateGroupController extends Controller
{
    public function __construct(
        private readonly UpdateGroupAction $action,
    ) {}

    public function __invoke(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $data = $request->validated();
        $group = $this->action->execute(
            $group,
            $data,
            (string) $request->user()->getAuthIdentifier(),
            (string) $data['reason'],
        );

        return GroupResource::make($group)->response();
    }
}
