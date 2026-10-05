<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Organization\Domain\Contracts\SchoolClockQueries;
use Modules\Sessions\Application\Services\SessionAccessDecision;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Http\Requests\ListSessionsRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * قائمة الحصص، بفلتر تاريخ اختياري (date=يوم واحد بتوقيت المؤسسة، أو from/to
 * لمدى صريح). بلا فلتر: نفس السلوك القديم — كل الحصص المرئية مرتبة تصاعديًا.
 */
final class ListSessionsController extends Controller
{
    public function __construct(
        private readonly SessionAccessDecision $access,
        private readonly SchoolClockQueries $clock,
    ) {}

    public function __invoke(ListSessionsRequest $request): mixed
    {
        Gate::authorize('viewAny', Session::class);

        $query = $this->access->scopeVisible(Session::query(), auth()->user());

        [$from, $to] = $this->resolveRange($request);
        if ($from !== null && $to !== null) {
            $query->whereBetween('scheduled_start', [$from, $to]);
        }

        $sessions = $query
            ->orderBy('scheduled_start')
            ->paginate((int) config('sessions.pagination.per_page'));

        return SessionResource::collection($sessions);
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function resolveRange(ListSessionsRequest $request): array
    {
        $date = $request->validated('date');
        if ($date !== null) {
            $organizationId = (string) $request->user()->organization_id;
            $timezone = $this->clock->forOrganization($organizationId)['timezone'];
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

            return [$start->utc(), $start->addDay()->subSecond()->utc()];
        }

        $from = $request->validated('from');
        $to = $request->validated('to');
        if ($from !== null && $to !== null) {
            return [CarbonImmutable::parse($from)->utc(), CarbonImmutable::parse($to)->utc()];
        }

        return [null, null];
    }
}
