<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\StartSessionAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\StartSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * بدء الحصة.
 */
final class StartSessionController extends Controller
{
    public function __construct(
        private readonly StartSessionAction $action,
    ) {}

    public function __invoke(StartSessionRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('start', $sessionModel);

        $this->action->execute($sessionModel);

        return new SessionResource($sessionModel->refresh());
    }
}
