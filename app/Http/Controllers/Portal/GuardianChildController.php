<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuardianChildController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request, string $studentId): Response
    {
        $org = (string) $request->user()?->getAttribute('organization_id');
        $uid = (string) $request->user()?->getAuthIdentifier();
        $locale = app()->getLocale();
        $child = $this->data->guardianChild($uid, $studentId, $locale, $org);
        abort_if($child === null, 404);
        $sessions = $this->data->guardianUpcomingSessions($studentId, $locale, $org);

        return Inertia::render('Guardian/Child/Show', ['child' => $child, 'attendanceRate' => $this->data->attendanceRate($studentId, $org), 'upcomingSessions' => array_slice($sessions, 0, 5), 'reports' => array_slice($this->data->monthlyReports($studentId, $locale, $org), 0, 2)]);
    }
}
