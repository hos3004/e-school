<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Application\Services\SessionAccessDecision;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * قائمة الحصص.
 */
final class ListSessionsController extends Controller
{
    public function __construct(private readonly SessionAccessDecision $access) {}

    public function __invoke(): mixed
    {
        Gate::authorize('viewAny', Session::class);

        $sessions = $this->access->scopeVisible(Session::query(), auth()->user())
            ->orderBy('scheduled_start')
            ->paginate((int) config('sessions.pagination.per_page'));

        return SessionResource::collection($sessions);
    }
}
