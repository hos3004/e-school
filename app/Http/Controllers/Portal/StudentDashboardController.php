<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentDashboardController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $studentId = $this->data->studentProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        if ($studentId === null) {
            return Inertia::render('Student/Dashboard', [
                'nextSession' => null,
                'weekSessions' => [],
                'attendanceRate' => null,
                'openAssignments' => [],
            ]);
        }

        $locale = app()->getLocale();
        $upcoming = $this->data->upcomingStudentSessions($studentId, $locale, $organizationId);
        $assignments = $this->data->studentAssignments($studentId, $locale, $organizationId);

        return Inertia::render('Student/Dashboard', [
            'nextSession' => $upcoming[0] ?? null,
            'weekSessions' => $this->data->studentWeekSessions(
                $studentId,
                $locale,
                (string) data_get($request->user(), 'timezone', 'UTC'),
                $organizationId,
            ),
            'attendanceRate' => $this->data->attendanceRate($studentId, $organizationId),
            'openAssignments' => $this->data->openAssignments($assignments),
        ]);
    }
}
