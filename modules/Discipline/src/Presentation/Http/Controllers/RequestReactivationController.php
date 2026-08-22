<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Application\Actions\RequestReactivationAction;
use Modules\Discipline\Presentation\Http\Requests\RequestReactivationRequest;
use Modules\Discipline\Presentation\Http\Resources\ReactivationRequestResource;

/**
 * تقديم طلب إعادة تفعيل — الطالب أو وليّه أو الإدارة بصلاحية مناسبة.
 */
final class RequestReactivationController extends Controller
{
    public function __construct(
        private readonly RequestReactivationAction $action,
    ) {}

    public function __invoke(RequestReactivationRequest $request): ReactivationRequestResource
    {
        $reactivation = $this->action->execute($request->validated());

        return new ReactivationRequestResource($reactivation);
    }
}
