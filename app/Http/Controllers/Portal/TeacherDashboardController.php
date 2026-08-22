<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherDashboardController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );
        $locale = app()->getLocale();

        return Inertia::render('Teacher/Dashboard', [
            'todaysSessions' => $staffId === null ? [] : $this->data->teacherTodaySessions(
                $staffId,
                $locale,
                (string) data_get($request->user(), 'timezone', 'UTC'),
                $organizationId,
            ),
            'pendingAttendance' => $staffId === null
                ? []
                : $this->data->teacherPendingAttendanceSessions(
                    $staffId,
                    $locale,
                    $organizationId,
                ),
            'lateReports' => $staffId === null
                ? []
                : $this->data->teacherLateReportSessions(
                    $staffId,
                    $locale,
                    $organizationId,
                ),
            'statusColors' => $this->data->statusColors(),
        ]);
    }
}
