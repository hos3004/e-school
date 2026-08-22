<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Resources\StaffProfileResource;

final class IndexStaffProfilesController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $this->authorize();

        $profiles = StaffProfile::query()
            ->active()
            ->orderBy('staff_code')
            ->paginate((int) config('staff.pagination.per_page', 25));

        return StaffProfileResource::collection($profiles);
    }

    private function authorize(): void
    {
        $user = request()?->user();

        abort_unless($user !== null && $user->can('staff.profile.view_any'), 403);
    }
}
