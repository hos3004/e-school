<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Application\Actions\WaiveViolationAction;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Presentation\Http\Requests\WaiveViolationRequest;
use Modules\Discipline\Presentation\Http\Resources\ViolationEventResource;

/**
 * العفو عن مخالفة — سبب موثَّق إلزامي.
 */
final class WaiveViolationController extends Controller
{
    public function __construct(
        private readonly WaiveViolationAction $action,
    ) {}

    public function __invoke(WaiveViolationRequest $request, ViolationEvent $violation): ViolationEventResource
    {
        $waived = $this->action->execute($violation, $request->validated());

        return new ViolationEventResource($waived);
    }
}
