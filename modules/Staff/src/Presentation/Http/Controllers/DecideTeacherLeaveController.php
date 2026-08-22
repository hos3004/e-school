<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Modules\Staff\Application\Actions\DecideTeacherLeave;
use Modules\Staff\Domain\Enums\TeacherLeaveStatus;
use Modules\Staff\Domain\Models\TeacherLeave;
use Modules\Staff\Presentation\Http\Requests\DecideTeacherLeaveRequest;
use Modules\Staff\Presentation\Http\Resources\TeacherLeaveResource;

final class DecideTeacherLeaveController
{
    public function __invoke(DecideTeacherLeaveRequest $request, TeacherLeave $leave, DecideTeacherLeave $action): TeacherLeaveResource
    {
        $validated = $request->validated();

        return new TeacherLeaveResource($action->execute(
            leave: $leave,
            decision: TeacherLeaveStatus::from($validated['decision']),
            approverId: (string) $request->user()?->getAuthIdentifier(),
        ));
    }
}
