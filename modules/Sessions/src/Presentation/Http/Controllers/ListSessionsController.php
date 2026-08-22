<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * قائمة الحصص.
 */
final class ListSessionsController extends Controller
{
    public function __invoke(): mixed
    {
        Gate::authorize('viewAny', Session::class);

        /** @var string $organizationId */
        $organizationId = auth()->user()->organization_id;

        $sessions = Session::query()
            ->forOrganization($organizationId)
            ->orderBy('scheduled_start')
            ->paginate((int) config('sessions.pagination.per_page'));

        return SessionResource::collection($sessions);
    }
}
