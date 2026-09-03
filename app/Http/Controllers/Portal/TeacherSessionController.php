<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Application\Queries\RecordingAccessCoordinator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;

final class TeacherSessionController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RecordingAdministrationQueries $recordings,
        private readonly RecordingAccessCoordinator $recordingAccess,
    ) {}

    public function __invoke(Request $request, string $id): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        if ($staffId === null) {
            return Inertia::render('Teacher/Sessions/Show', [
                'session' => null,
                'attendance' => [],
                'attendanceStatuses' => [],
                'statusColors' => $this->data->statusColors(),
                'attendanceUpdateUrl' => '',
                'reportSubmitUrl' => '',
                'initialReport' => null,
            ]);
        }

        $session = $this->data->teacherSession(
            $staffId,
            $id,
            app()->getLocale(),
            $organizationId,
        );

        abort_if($session === null, 404);

        $session['joinUrl'] = route('portal.teacher.sessions.join', ['session' => $id]);
        $session['canJoinAt'] = CarbonImmutable::parse($session['startsAt'], 'UTC')
            ->subMinutes(max(0, (int) config('virtual-classroom.join_window.teacher_before_minutes')))
            ->toIso8601String();
        $session['canJoinUntil'] = CarbonImmutable::parse($session['endsAt'], 'UTC')
            ->addMinutes(max(0, (int) config('virtual-classroom.join_window.after_minutes')))
            ->toIso8601String();
        $session['canJoin'] = SessionStatus::tryFrom((string) $session['status'])?->allowsJoining() === true
            && CarbonImmutable::now('UTC')->betweenIncluded(
                CarbonImmutable::parse($session['canJoinAt'], 'UTC'),
                CarbonImmutable::parse($session['canJoinUntil'], 'UTC'),
            );
        $user = $request->user();
        $recording = $user === null ? null : collect($this->recordings->forSession($organizationId, $id))
            ->first(fn (mixed $candidate): bool => $this->recordingAccess->canWatch($user, $candidate));
        $session['recordingUrl'] = $recording === null
            ? null
            : URL::temporarySignedRoute(
                'portal.recordings.watch',
                now()->addMinutes(max(1, (int) config('recordings.access.signed_url_ttl_minutes'))),
                ['recording' => $recording->id],
            );

        return Inertia::render('Teacher/Sessions/Show', [
            'session' => $session,
            'attendance' => $this->data->teacherAttendance($id, $organizationId),
            'attendanceStatuses' => $this->data->attendanceStatuses(),
            'statusColors' => $this->data->statusColors(),
            'attendanceUpdateUrl' => route('portal.teacher.sessions.attendance.store', ['session' => $id]),
            'reportSubmitUrl' => route('portal.teacher.sessions.report.store', ['session' => $id]),
            'initialReport' => $this->data->teacherInitialReport(
                $id,
                $staffId,
            ),
            'postponementRequestUrl' => route('portal.teacher.sessions.postponement-requests.store', ['session' => $id]),
            'postponementRequest' => $this->data->postponementForSession(
                $id,
                (string) $request->user()?->getAuthIdentifier(),
                $organizationId,
            ),
            'canRequestPostponement' => (bool) $request->user()?->can('session.postpone.request')
                && in_array((string) $session['status'], [SessionStatus::Scheduled->value, SessionStatus::Confirmed->value], true),
            'canSubmitReport' => (bool) $request->user()?->can('session_report.create')
                && in_array((string) $session['status'], [SessionStatus::AwaitingReview->value, SessionStatus::Completed->value], true),
        ]);
    }
}
