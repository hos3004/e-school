<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherAvailabilityController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $user?->getAuthIdentifier(),
            $organizationId,
        );

        return Inertia::render('Teacher/Availability', [
            'availability' => $staffProfileId === null
                ? []
                : $this->data->teacherAvailability($staffProfileId),
            'hasProfile' => $staffProfileId !== null,
            'timezones' => $this->data->timezoneOptions(),
            'defaultTimezone' => (string) ($user?->getAttribute('timezone') ?: 'UTC'),
            'storeUrl' => route('portal.teacher.availability.store'),
            'canManage' => $request->user()?->can('staff.availability.create') === true,
        ]);
    }
}
