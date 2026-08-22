<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Modules\Staff\Application\Actions\TerminateStaffProfile;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Requests\TerminateStaffProfileRequest;
use Modules\Staff\Presentation\Http\Resources\StaffProfileResource;

final class TerminateStaffProfileController
{
    public function __invoke(TerminateStaffProfileRequest $request, StaffProfile $profile, TerminateStaffProfile $action): StaffProfileResource
    {
        $validated = $request->validated();

        return new StaffProfileResource($action->execute($profile, $validated['reason']));
    }
}
