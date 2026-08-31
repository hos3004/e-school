<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Actions\ExcuseAbsenceAction;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\ExcuseAbsenceRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * قبول غياب الطالب بعذر.
 */
final class ExcuseAbsenceController extends Controller
{
    public function __construct(
        private readonly ExcuseAbsenceAction $action,
    ) {}

    public function __invoke(ExcuseAbsenceRequest $request, string $session): SessionResource
    {
        $sessionModel = Session::query()->findOrFail($session);

        Gate::authorize('excuse', $sessionModel);

        $this->action->execute(
            $sessionModel,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return new SessionResource($sessionModel->refresh());
    }
}
