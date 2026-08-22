<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Resources\StaffProfileResource;

final class ShowStaffProfileController
{
    public function __invoke(Request $request, StaffProfile $profile): StaffProfileResource
    {
        abort_unless($request->user()?->can('view', $profile) ?? false, 403);

        return new StaffProfileResource($profile);
    }
}
