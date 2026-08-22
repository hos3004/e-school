<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\MarkNoShowAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\MarkNoShowRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * رصد غياب الطالب بدون إذن.
 */
final class MarkNoShowController extends Controller
{
    public function __construct(
        private readonly MarkNoShowAction $action,
    ) {}

    public function __invoke(MarkNoShowRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('markNoShow', $sessionModel);

        $this->action->execute($sessionModel, $request->validated('reason'));

        return new SessionResource($sessionModel->refresh());
    }
}
