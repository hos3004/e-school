<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\EndSessionAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\EndSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * إنهاء الحصة وتركها بانتظار الاعتماد.
 */
final class EndSessionController extends Controller
{
    public function __construct(
        private readonly EndSessionAction $action,
    ) {}

    public function __invoke(EndSessionRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('end', $sessionModel);

        $this->action->execute(
            $sessionModel,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return new SessionResource($sessionModel->refresh());
    }
}
