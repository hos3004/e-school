<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Assessments\Application\Actions\CreateAssessmentAction;
use Modules\Assessments\Presentation\Http\Requests\StoreAssessmentRequest;
use Modules\Assessments\Presentation\Http\Resources\AssessmentResource;

/**
 * إنشاء اختبار جديد.
 */
final class StoreAssessmentController extends Controller
{
    public function __construct(
        private readonly CreateAssessmentAction $action,
    ) {}

    public function __invoke(StoreAssessmentRequest $request): AssessmentResource
    {
        $user = $request->user();

        // السبب يخص قيد التدقيق وحده — لا يُمرَّر كسمة على نموذج الاختبار.
        $assessment = $this->action->execute([
            ...$request->safe()->except('reason'),
            'organization_id' => (string) $user?->organization_id,
        ],
            actorId: (string) $user?->getAuthIdentifier(),
            reason: (string) $request->validated('reason'),
            canManageAll: $user?->can('settings.manage') === true
                || $user?->can('student.update') === true
                || $user?->can('message.moderate') === true,
        );

        return new AssessmentResource($assessment);
    }
}
