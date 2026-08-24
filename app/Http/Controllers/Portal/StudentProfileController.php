<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentProfileController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $userId = (string) $user?->getAuthIdentifier();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $studentProfileId = $this->data->studentProfileId($userId, $organizationId);

        return Inertia::render('Student/Profile', [
            'student' => $this->data->studentProfile(
                $userId,
                $organizationId,
                app()->getLocale(),
            ),
            'account' => $this->data->accountSettings($userId, $organizationId),
            'timezones' => $this->data->timezoneOptions(),
            'attendanceRate' => $studentProfileId === null
                ? null
                : $this->data->attendanceRate($studentProfileId, $organizationId),
            'updateUrl' => route('portal.student.profile.update'),
            'passwordUrl' => route('portal.student.profile.password'),
        ]);
    }
}
