<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * عرض مجموعة واحدة.
 */
final class ShowGroupController extends Controller
{
    public function __invoke(Group $group): GroupResource
    {
        $this->authorizeAccess($group);

        return GroupResource::make($group);
    }

    private function authorizeAccess(Group $group): void
    {
        abort_unless(
            request()->user()?->can('view', $group) ?? false,
            403,
        );
    }
}
