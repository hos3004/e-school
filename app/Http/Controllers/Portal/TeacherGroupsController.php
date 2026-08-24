<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherGroupsController extends Controller
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

        return Inertia::render('Teacher/Groups', [
            'groups' => $staffProfileId === null
                ? []
                : $this->data->teacherGroupsDetailed(
                    $staffProfileId,
                    $organizationId,
                    app()->getLocale(),
                ),
        ]);
    }
}
