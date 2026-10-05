<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherGroupController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request, string $group): Response
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $user?->getAuthIdentifier(),
            $organizationId,
        );

        abort_if($staffProfileId === null, 404);

        $details = $this->data->teacherGroupDetailed(
            $staffProfileId,
            $group,
            $organizationId,
            app()->getLocale(),
        );

        abort_if($details === null, 404);

        return Inertia::render('Teacher/Groups/Show', [
            'group' => $details,
            'statusColors' => $this->data->statusColors(),
        ]);
    }
}
