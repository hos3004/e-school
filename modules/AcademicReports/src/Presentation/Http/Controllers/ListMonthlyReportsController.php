<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\AcademicReports\Presentation\Http\Resources\MonthlyReportResource;

/**
 * قائمة التقارير الشهرية — محصورة بمؤسسة المستخدم دائمًا.
 */
final class ListMonthlyReportsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MonthlyReport::class);

        $reports = MonthlyReport::query()
            ->forOrganization((string) $request->user()?->organization_id)
            ->when(
                $request->filled('student_profile_id'),
                fn ($query) => $query->forStudent((string) $request->string('student_profile_id')),
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', (string) $request->string('status')),
            )
            ->when(
                $request->filled(['period_year', 'period_month']),
                fn ($query) => $query->inPeriod(
                    $request->integer('period_year'),
                    $request->integer('period_month'),
                ),
            )
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return MonthlyReportResource::collection($reports);
    }
}
