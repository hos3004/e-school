<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentProgramsController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $studentId = $this->data->studentProfileId((string) $user?->getAuthIdentifier(), $organizationId);

        return Inertia::render('Student/Programs', [
            'programs' => $studentId === null ? [] : $this->data->studentPrograms($studentId, $organizationId, app()->getLocale()),
        ]);
    }
}
