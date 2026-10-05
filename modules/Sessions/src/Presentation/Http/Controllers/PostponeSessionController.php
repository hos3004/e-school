<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\PostponeSessionAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\PostponeSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * تأجيل حصة وإنشاء حصة تلافي.
 */
final class PostponeSessionController extends Controller
{
    public function __construct(
        private readonly PostponeSessionAction $action,
    ) {}

    public function __invoke(PostponeSessionRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('postpone', $sessionModel);

        $this->action->execute(
            $sessionModel,
            (string) $request->validated('makeup_start'),
            (string) $request->validated('makeup_end'),
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return new SessionResource($sessionModel->refresh());
    }
}
