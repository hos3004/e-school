<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Staff\Application\Actions\RequestTeacherLeave;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Presentation\Http\Requests\StoreTeacherLeaveRequest;
use Modules\Staff\Presentation\Http\Resources\TeacherLeaveResource;
use Symfony\Component\HttpFoundation\Response;

final class StoreTeacherLeaveController
{
    public function __invoke(StoreTeacherLeaveRequest $request, RequestTeacherLeave $action): JsonResponse
    {
        $validated = $request->validated();

        /** @var StaffProfile $profile */
        $profile = StaffProfile::query()->findOrFail($validated['staff_profile_id']);

        $leave = $action->execute(
            profile: $profile,
            startsAt: $validated['starts_at'],
            endsAt: $validated['ends_at'],
            reason: $validated['reason'] ?? null,
        );

        return new TeacherLeaveResource($leave)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
