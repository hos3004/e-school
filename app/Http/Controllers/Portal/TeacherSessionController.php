<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherSessionController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request, string $id): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        if ($staffId === null) {
            return Inertia::render('Teacher/Sessions/Show', [
                'session' => null,
                'attendance' => [],
                'attendanceStatuses' => [],
                'statusColors' => $this->data->statusColors(),
                'attendanceUpdateUrl' => '',
                'reportSubmitUrl' => '',
                'initialReport' => null,
            ]);
        }

        $session = $this->data->teacherSession(
            $staffId,
            $id,
            app()->getLocale(),
            $organizationId,
        );

        abort_if($session === null, 404);

        return Inertia::render('Teacher/Sessions/Show', [
            'session' => $session,
            'attendance' => $this->data->teacherAttendance($id, $organizationId),
            'attendanceStatuses' => $this->data->attendanceStatuses(),
            'statusColors' => $this->data->statusColors(),
            'attendanceUpdateUrl' => route('sessions.attendance', ['session' => $id]),
            'reportSubmitUrl' => route('academicreports.session_reports.store'),
            'initialReport' => $this->data->teacherInitialReport(
                $id,
                $staffId,
                $organizationId,
            ),
        ]);
    }
}
