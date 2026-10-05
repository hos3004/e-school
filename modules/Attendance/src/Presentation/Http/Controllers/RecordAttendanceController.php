<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Http\Requests\RecordAttendanceRequest;
use Modules\Attendance\Presentation\Http\Resources\AttendanceResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * رصد حضور طالب — POST /api/attendances.
 */
final class RecordAttendanceController extends Controller
{
    public function __construct(
        private readonly RecordAttendanceAction $action,
    ) {}

    public function __invoke(RecordAttendanceRequest $request): Response
    {
        /** @var Attendance $attendance */
        $attendance = $this->action->execute(
            sessionParticipantId: (string) $request->validated('session_participant_id'),
            attendedMinutes: (int) $request->validated('attended_minutes'),
            sessionMinutes: (int) $request->validated('session_minutes'),
            joinedAfterMinutes: (int) $request->validated('joined_after_minutes', 0),
            leftBeforeMinutes: (int) $request->validated('left_before_minutes', 0),
            organizationId: (string) $request->user()?->getAttribute('organization_id'),
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: (string) $request->validated('reason'),
        );

        return AttendanceResource::make($attendance)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
