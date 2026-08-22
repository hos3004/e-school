<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\CreateGroupAction;
use Modules\Groups\Presentation\Http\Requests\CreateGroupRequest;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * إنشاء مجموعة جديدة.
 */
final class CreateGroupController extends Controller
{
    public function __construct(
        private readonly CreateGroupAction $action,
    ) {}

    public function __invoke(CreateGroupRequest $request): JsonResponse
    {
        $group = $this->action->execute($request->validated());

        return GroupResource::make($group)
            ->response()
            ->setStatusCode(201);
    }
}
