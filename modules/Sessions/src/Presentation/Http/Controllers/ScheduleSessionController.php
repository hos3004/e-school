<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Sessions\Application\Actions\ScheduleSessionAction;
use Modules\Sessions\Presentation\Http\Requests\StoreSessionRequest;
use Modules\Sessions\Presentation\Http\Resources\SessionResource;

/**
 * إنشاء حصة جديدة.
 */
final class ScheduleSessionController extends Controller
{
    public function __construct(
        private readonly ScheduleSessionAction $action,
    ) {}

    public function __invoke(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->action->execute($request->validated());

        return SessionResource::make($session)
            ->response()
            ->setStatusCode(201);
    }
}
