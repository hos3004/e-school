<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Resources\StaffProfileResource;

final class IndexStaffProfilesController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        abort_unless($user !== null && $user->can('viewAny', StaffProfile::class), 403);

        $organizationId = data_get($user, 'organization_id');

        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        $profiles = StaffProfile::query()
            ->forOrganization($organizationId)
            ->active()
            ->orderBy('staff_code')
            ->paginate((int) config('staff.pagination.per_page', 25));

        return StaffProfileResource::collection($profiles);
    }
}
