<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\CancelSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * إلغاء حصة.
 */
final class CancelSessionController extends Controller
{
    public function __construct(
        private readonly CancelSessionAction $action,
    ) {}

    public function __invoke(CancelSessionRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('cancel', $sessionModel);

        $this->action->execute(
            $sessionModel,
            SessionStatus::from($request->validated('as')),
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return new SessionResource($sessionModel->refresh());
    }
}
