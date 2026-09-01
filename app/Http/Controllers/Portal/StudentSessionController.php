<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Application\Queries\RecordingAccessCoordinator;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;

final class StudentSessionController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RecordingAdministrationQueries $recordings,
        private readonly RecordingAccessCoordinator $recordingAccess,
    ) {}

    public function __invoke(Request $request, string $id): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $studentId = $this->data->studentProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        if ($studentId === null) {
            return Inertia::render('Student/Sessions/Show', ['session' => null]);
        }

        $session = $this->data->studentSession(
            $studentId,
            $id,
            app()->getLocale(),
            $organizationId,
        );

        abort_if($session === null, 404);

        $session['joinUrl'] = route('portal.student.sessions.join', ['session' => $id]);
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

        return Inertia::render('Student/Sessions/Show', [
            'session' => $session,
            'postponementRequestUrl' => route('portal.student.sessions.postponement-requests.store', ['session' => $id]),
            'postponementRequest' => $this->data->postponementForSession(
                $id,
                (string) $request->user()?->getAuthIdentifier(),
                $organizationId,
            ),
            'canRequestPostponement' => (bool) $request->user()?->can('session.postpone.request')
                && in_array((string) $session['status'], ['scheduled', 'confirmed'], true),
        ]);
    }
}
