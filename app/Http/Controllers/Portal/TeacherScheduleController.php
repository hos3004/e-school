<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherScheduleController extends Controller
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

        return Inertia::render('Teacher/Schedule', [
            'sessions' => $staffId === null
                ? []
                : $this->data->teacherScheduleSessions(
                    $staffId,
                    app()->getLocale(),
                    $organizationId,
                ),
            'statusColors' => $this->data->statusColors(),
        ]);
    }
}
