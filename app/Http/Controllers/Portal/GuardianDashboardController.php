<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuardianDashboardController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request): Response
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $locale = app()->getLocale();
        $children = $this->data->guardianChildren($userId, $locale, $organizationId);
        $requestedChildId = $request->query('child');
        $selectedChild = null;

        if ($requestedChildId !== null) {
            abort_unless(is_string($requestedChildId), 404);
            $selectedChild = $this->data->guardianChild(
                $userId,
                $requestedChildId,
                $locale,
                $organizationId,
            );
            abort_if($selectedChild === null, 404);
        } elseif ($children !== []) {
            $selectedChild = $children[0];
        }

        if ($selectedChild === null) {
            return Inertia::render('Guardian/Dashboard', [
                'children' => $children,
                'selectedChild' => null,
                'nextSession' => null,
                'attendanceRate' => null,
                'openAssignments' => [],
                'reports' => [],
            ]);
        }

        $studentId = (string) $selectedChild['id'];
        $upcoming = $this->data->upcomingStudentSessions($studentId, $locale, $organizationId);
        $assignments = $this->data->studentAssignments($studentId, $locale, $organizationId);

        return Inertia::render('Guardian/Dashboard', [
            'children' => $children,
            'selectedChild' => $selectedChild,
            'nextSession' => $upcoming[0] ?? null,
            'attendanceRate' => $this->data->attendanceRate($studentId, $organizationId),
            'openAssignments' => $this->data->openAssignments($assignments),
            'reports' => array_slice(
                $this->data->monthlyReports($studentId, $locale, $organizationId),
                0,
                3,
            ),
        ]);
    }
}
