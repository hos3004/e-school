<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\ArchiveGroupAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Requests\ArchiveGroupRequest;

/**
 * أرشفة مجموعة مع تسجيل السبب.
 */
final class ArchiveGroupController extends Controller
{
    public function __construct(
        private readonly ArchiveGroupAction $action,
    ) {}

    public function __invoke(ArchiveGroupRequest $request, Group $group): JsonResponse
    {
        $this->action->execute(
            $group,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return response()->json(null, 204);
    }
}
