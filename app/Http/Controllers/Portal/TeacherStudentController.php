<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherStudentController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request, string $student): Response
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $user?->getAuthIdentifier(),
            $organizationId,
        );

        abort_if($staffProfileId === null, 404);

        $profile = $this->data->teacherStudentProfile(
            $staffProfileId,
            $student,
            $organizationId,
            app()->getLocale(),
        );

        abort_if($profile === null, 404);

        return Inertia::render('Teacher/Students/Show', [
            'student' => $profile,
        ]);
    }
}
