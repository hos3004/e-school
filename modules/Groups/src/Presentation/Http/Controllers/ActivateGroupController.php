<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * تفعيل مجموعة قيد التخطيط.
 */
final class ActivateGroupController extends Controller
{
    public function __construct(
        private readonly ActivateGroupAction $action,
    ) {}

    public function __invoke(Group $group): JsonResponse
    {
        abort_unless(
            request()->user()?->can('activate', $group) ?? false,
            403,
        );

        $group = $this->action->execute($group);

        return GroupResource::make($group)->response();
    }
}
