<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Resources\AssessmentResource;

/**
 * قائمة الاختبارات لمؤسسة المستخدم.
 */
final class ListAssessmentsController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Assessment::class);

        $assessments = Assessment::query()
            ->forOrganization((string) auth()->user()?->organization_id)
            ->orderByDesc('created_at')
            ->get();

        return AssessmentResource::collection($assessments);
    }
}
