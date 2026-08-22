<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentSessionController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request, string $id): Response
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $studentId = $this->data->studentProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        if ($studentId === null) {
            return Inertia::render('Student/Sessions/Show', ['session' => null]);
        }

        $session = $this->data->studentSession(
            $studentId,
            $id,
            app()->getLocale(),
            $organizationId,
        );

        abort_if($session === null, 404);

        return Inertia::render('Student/Sessions/Show', ['session' => $session]);
    }
}
