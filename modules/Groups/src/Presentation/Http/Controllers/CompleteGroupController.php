<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\CompleteGroupAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * إتمام مجموعة نشطة — حالة نهائية.
 */
final class CompleteGroupController extends Controller
{
    public function __construct(
        private readonly CompleteGroupAction $action,
    ) {}

    public function __invoke(Group $group): JsonResponse
    {
        abort_unless(
            request()->user()?->can('complete', $group) ?? false,
            403,
        );

        $group = $this->action->execute($group);

        return GroupResource::make($group)->response();
    }
}
