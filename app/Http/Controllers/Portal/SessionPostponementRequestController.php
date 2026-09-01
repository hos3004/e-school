<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\RequestSessionPostponementRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Modules\Scheduling\Application\Actions\RequestPostponement;

final class SessionPostponementRequestController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RequestPostponement $requestPostponement,
    ) {}

    public function student(RequestSessionPostponementRequest $request, string $session): RedirectResponse
    {
        return $this->submit($request, $session, false);
    }

    public function teacher(RequestSessionPostponementRequest $request, string $session): RedirectResponse
    {
        return $this->submit($request, $session, true);
    }

    private function submit(
        RequestSessionPostponementRequest $request,
        string $session,
        bool $asTeacher,
    ): RedirectResponse {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $studentProfileId = $asTeacher ? null : $this->data->studentProfileId($actorId, $organizationId);
        $staffProfileId = $asTeacher ? $this->data->staffProfileId($actorId, $organizationId) : null;

        abort_if($organizationId === '' || ($asTeacher ? $staffProfileId === null : $studentProfileId === null), 403);
        $validated = $request->validated();

        $this->requestPostponement->execute(
            organizationId: $organizationId,
            sessionId: $session,
            requestedBy: $actorId,
            studentProfileId: $studentProfileId,
            proposedStart: CarbonImmutable::parse((string) $validated['proposed_start'], 'UTC'),
            reason: (string) $validated['reason'],
            requestingStaffProfileId: $staffProfileId,
        );

        return back()->with('success', __('scheduling::messages.postponement_requested'));
    }
}
