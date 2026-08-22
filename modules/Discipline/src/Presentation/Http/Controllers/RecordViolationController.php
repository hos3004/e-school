<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Presentation\Http\Requests\RecordViolationRequest;
use Modules\Discipline\Presentation\Http\Resources\ViolationEventResource;

/**
 * تسجيل مخالفة — المتحكم رفيع: تحقّق ثم إجراء ثم Resource.
 */
final class RecordViolationController extends Controller
{
    public function __construct(
        private readonly RecordViolationAction $action,
    ) {}

    public function __invoke(RecordViolationRequest $request): ViolationEventResource
    {
        $violation = $this->action->execute($request->validated());

        return new ViolationEventResource($violation);
    }
}
