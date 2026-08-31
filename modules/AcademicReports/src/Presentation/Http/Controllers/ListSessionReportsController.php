<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Presentation\Http\Resources\SessionReportResource;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;

/**
 * قائمة تقارير الحصص — نطاق المعلم نفسه إن كان معلمًا.
 */
final class ListSessionReportsController extends Controller
{
    public function __construct(
        private readonly SessionAdministrationQueries $sessions,
        private readonly StaffQueries $staff,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SessionReport::class);

        $organizationId = data_get($request->user(), 'organization_id');
        $sessionIds = is_string($organizationId) && $organizationId !== ''
            ? $this->sessions->sessionIdsForOrganization($organizationId)
            : [];

        $query = SessionReport::query()
            ->with('students')
            ->whereIn('session_id', $sessionIds)
            ->when(
                $request->filled('session_id'),
                fn ($query) => $query->forSession((string) $request->string('session_id')),
            )
            ->when(
                $request->filled('staff_profile_id'),
                fn ($query) => $query->forStaff((string) $request->string('staff_profile_id')),
            )
            ->submitted();

        /*
         * `session_report.view` يجمع نطاقات متعددة في المصفوفة. من لا يملك
         * staff.view.any ويملك ملف معلم نشطًا داخل المؤسسة يُحصر في تقاريره.
         * الطلاب والأوصياء يحتاجون عقد ملكية منفصل؛ إلى أن يتوفر يبقون على
         * أقل حد آمن متاح هنا: حصص مؤسستهم فقط.
         */
        if (is_string($organizationId)
            && $organizationId !== ''
            && !$request->user()->can('staff.view.any')) {
            $profile = $this->staff->findActiveProfileForUser((string) $request->user()->getAuthIdentifier());
            $staffProfileId = is_array($profile) ? ($profile['id'] ?? null) : null;

            if (is_string($staffProfileId)
                && $staffProfileId !== ''
                && $this->staff->isActiveTeacherForOrganization($organizationId, $staffProfileId)) {
                $query->forStaff($staffProfileId);
            }
        }

        $reports = $query
            ->latest('submitted_at')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return SessionReportResource::collection($reports);
    }
}
