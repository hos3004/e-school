<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentReportsController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $studentId = $this->data->studentProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        return Inertia::render('Student/Reports', [
            'reports' => $studentId === null
                ? []
                : $this->data->monthlyReports(
                    $studentId,
                    app()->getLocale(),
                    $organizationId,
                ),
        ]);
    }
}
