<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Application\Actions\CancelReactivationAction;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Presentation\Http\Requests\CancelReactivationRequest;
use Modules\Discipline\Presentation\Http\Resources\ReactivationRequestResource;

/**
 * سحب طلب إعادة التفعيل من مقدِّمه قبل القرار.
 */
final class CancelReactivationController extends Controller
{
    public function __construct(
        private readonly CancelReactivationAction $action,
    ) {}

    public function __invoke(CancelReactivationRequest $request, ReactivationRequest $reactivation): ReactivationRequestResource
    {
        $cancelled = $this->action->execute($reactivation);

        return new ReactivationRequestResource($cancelled);
    }
}
