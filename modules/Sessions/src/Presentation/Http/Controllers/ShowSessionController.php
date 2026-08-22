<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * عرض حصة واحدة مع مشاركيها.
 */
final class ShowSessionController extends Controller
{
    public function __invoke(string $session): mixed
    {
        $sessionModel = Session::query()->with('participants')->findOrFail($session);

        Gate::authorize('view', $sessionModel);

        return new SessionResource($sessionModel);
    }
}
