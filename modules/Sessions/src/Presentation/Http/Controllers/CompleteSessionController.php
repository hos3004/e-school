<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\CompleteSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * اعتماد الحصة وقفلها.
 */
final class CompleteSessionController extends Controller
{
    public function __construct(
        private readonly CompleteSessionAction $action,
    ) {}

    public function __invoke(CompleteSessionRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('complete', $sessionModel);

        $this->action->execute(
            $sessionModel,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return new SessionResource($sessionModel->refresh());
    }
}
