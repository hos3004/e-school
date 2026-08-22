<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Discipline\Application\Actions\DecideReactivationAction;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Presentation\Http\Requests\DecideReactivationRequest;
use Modules\Discipline\Presentation\Http\Resources\ReactivationRequestResource;

/**
 * حسم طلب إعادة التفعيل — قبول أو رفض من الإدارة.
 */
final class DecideReactivationController extends Controller
{
    public function __construct(
        private readonly DecideReactivationAction $action,
    ) {}

    public function __invoke(DecideReactivationRequest $request, ReactivationRequest $reactivation): ReactivationRequestResource
    {
        $decided = $this->action->execute($reactivation, [
            'decision' => ReactivationStatus::from((string) $request->string('decision')),
            'decision_note' => (string) $request->string('decision_note'),
            'assessment_attempt_id' => $request->filled('assessment_attempt_id')
                ? (string) $request->string('assessment_attempt_id')
                : null,
        ]);

        return new ReactivationRequestResource($decided);
    }
}
