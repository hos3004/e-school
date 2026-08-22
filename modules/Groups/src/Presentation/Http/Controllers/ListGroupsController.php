<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * قائمة المجموعات — مرتّبة بالأحدث، مع فلترة اختيارية بالمؤسسة.
 */
final class ListGroupsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('viewAny', Group::class) ?? false,
            403,
        );

        $groups = Group::query()
            ->withCount(['memberships' => fn ($query) => $query->whereNull('left_at')])
            ->when(
                $request->filled('organization_id'),
                fn ($query) => $query->forOrganization((string) $request->string('organization_id')),
            )
            ->latest()
            ->paginate((int) $request->query('per_page', '15'));

        return GroupResource::collection($groups);
    }
}
