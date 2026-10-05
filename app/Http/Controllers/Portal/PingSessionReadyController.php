<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\PingSessionReadyRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Sessions\Application\Actions\PingSessionReadyAction;

final class PingSessionReadyController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly PingSessionReadyAction $ping,
    ) {}

    public function __invoke(PingSessionReadyRequest $request, string $session): RedirectResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $studentProfileId = $this->data->studentProfileId($actorId, $organizationId);
        abort_if($organizationId === '' || $studentProfileId === null, 403);

        $this->ping->execute(
            organizationId: $organizationId,
            sessionId: $session,
            studentProfileId: $studentProfileId,
            actorId: $actorId,
        );

        return back()->with('success', __('sessions::messages.ready_ping_sent'));
    }
}
