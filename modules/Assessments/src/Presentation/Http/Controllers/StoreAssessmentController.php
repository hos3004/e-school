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
        $assessment = $this->action->execute([
            ...$request->validated(),
            'organization_id' => (string) $request->user()?->organization_id,
        ]);

        return new AssessmentResource($assessment);
    }
}
