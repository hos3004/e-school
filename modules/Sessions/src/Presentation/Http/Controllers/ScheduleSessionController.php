<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Sessions\Application\Actions\ScheduleSessionAction;
use Modules\Sessions\Application\Services\SessionAccessDecision;
use Modules\Sessions\Presentation\Http\Requests\StoreSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * إنشاء حصة جديدة.
 */
final class ScheduleSessionController extends Controller
{
    public function __construct(
        private readonly ScheduleSessionAction $action,
        private readonly SessionAccessDecision $access,
    ) {}

    public function __invoke(StoreSessionRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['reason']);
        $data['organization_id'] = (string) $request->user()->getAttribute('organization_id');
        abort_unless(
            $this->access->canCreate($request->user(), (string) $data['staff_profile_id']),
            403,
        );
        $session = $this->action->execute(
            $data,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return SessionResource::make($session)
            ->response()
            ->setStatusCode(201);
    }
}
