<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherProfileController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $userId = (string) $user?->getAuthIdentifier();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId($userId, $organizationId);

        return Inertia::render('Teacher/Profile', [
            'teacher' => $this->data->teacherProfile($userId, $organizationId),
            'account' => $this->data->accountSettings($userId, $organizationId),
            'timezones' => $this->data->timezoneOptions(),
            'qualifications' => $staffProfileId === null
                ? []
                : $this->data->teacherQualifications($staffProfileId, app()->getLocale()),
            'availability' => $staffProfileId === null
                ? []
                : $this->data->teacherAvailability($staffProfileId),
            'updateUrl' => route('portal.teacher.profile.update'),
            'passwordUrl' => route('portal.teacher.profile.password'),
            'availabilityUrl' => route('portal.teacher.availability'),
        ]);
    }
}
