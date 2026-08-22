<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GuardianReportsController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
    ) {}

    public function __invoke(Request $request, string $studentId): Response
    {
        $locale = app()->getLocale();
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $child = $this->data->guardianChild(
            (string) $request->user()?->getAuthIdentifier(),
            $studentId,
            $locale,
            $organizationId,
        );

        abort_if($child === null, 404);

        return Inertia::render('Guardian/Child/Reports', [
            'selectedChild' => $child,
            'reports' => $this->data->monthlyReports($studentId, $locale, $organizationId),
        ]);
    }
}
