<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Requests\StoreTeacherAvailabilityRequest;
use Modules\Staff\Presentation\Http\Resources\TeacherAvailabilityResource;
use Symfony\Component\HttpFoundation\Response;

final class StoreTeacherAvailabilityController
{
    public function __invoke(StoreTeacherAvailabilityRequest $request, SetTeacherAvailability $action): TeacherAvailabilityResource
    {
        $validated = $request->validated();

        /** @var StaffProfile $profile */
        $profile = StaffProfile::query()->findOrFail($validated['staff_profile_id']);

        $availability = $action->execute(
            profile: $profile,
            weekday: (int) $validated['weekday'],
            startTime: (string) $validated['start_time'],
            endTime: (string) $validated['end_time'],
            timezone: (string) $validated['timezone'],
            effectiveFrom: $validated['effective_from'],
            effectiveTo: $validated['effective_to'] ?? null,
        );

        return new TeacherAvailabilityResource($availability)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
