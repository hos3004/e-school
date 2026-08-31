<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\ConfirmSessionAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\ConfirmSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * تأكيد حصة.
 */
final class ConfirmSessionController extends Controller
{
    public function __construct(
        private readonly ConfirmSessionAction $action,
    ) {}

    public function __invoke(ConfirmSessionRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('confirm', $sessionModel);

        $this->action->execute(
            $sessionModel,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return new SessionResource($sessionModel->refresh());
    }
}
