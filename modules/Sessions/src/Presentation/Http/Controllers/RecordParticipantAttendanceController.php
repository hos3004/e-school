<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\RecordParticipantAttendanceAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Presentation\Http\Requests\RecordParticipantAttendanceRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionParticipantResource;

/**
 * رصد حضور المشارك (دخول/خروج).
 */
final class RecordParticipantAttendanceController extends Controller
{
    public function __construct(
        private readonly RecordParticipantAttendanceAction $action,
    ) {}

    public function __invoke(RecordParticipantAttendanceRequest $request, string $session): SessionParticipantResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('recordAttendance', [$sessionModel]);

        /** @var SessionParticipant $participant */
        $participant = $sessionModel->participants()->findOrFail((string) $request->validated('participant_id'));

        $participant = $this->action->execute($sessionModel, $participant, (string) $request->validated('type'));

        return new SessionParticipantResource($participant);
    }
}
