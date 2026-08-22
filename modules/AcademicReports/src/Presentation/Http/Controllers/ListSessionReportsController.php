<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Presentation\Http\Resources\SessionReportResource;

/**
 * قائمة تقارير الحصص — نطاق المعلم نفسه إن كان معلمًا.
 */
final class ListSessionReportsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SessionReport::class);

        $reports = SessionReport::query()
            ->with('students')
            ->when(
                $request->filled('session_id'),
                fn ($query) => $query->forSession((string) $request->string('session_id')),
            )
            ->when(
                $request->filled('staff_profile_id'),
                fn ($query) => $query->forStaff((string) $request->string('staff_profile_id')),
            )
            ->submitted()
            ->latest('submitted_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return SessionReportResource::collection($reports);
    }
}
