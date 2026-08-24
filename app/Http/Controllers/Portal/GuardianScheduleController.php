<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuardianScheduleController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request, string $studentId): Response
    {
        $org = (string) $request->user()?->getAttribute('organization_id');
        $uid = (string) $request->user()?->getAuthIdentifier();
        $locale = app()->getLocale();
        $child = $this->data->guardianChild($uid, $studentId, $locale, $org);
        abort_if($child === null, 404);

        return Inertia::render('Guardian/Child/Schedule', ['selectedChild' => $child, 'sessions' => $this->data->guardianUpcomingSessions($studentId, $locale, $org)]);
    }
}
