<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Resources\AssessmentResource;

/**
 * عرض اختبار واحد.
 */
final class ShowAssessmentController extends Controller
{
    public function __invoke(string $assessment): AssessmentResource
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('view', $assessmentModel);

        return new AssessmentResource($assessmentModel);
    }
}
