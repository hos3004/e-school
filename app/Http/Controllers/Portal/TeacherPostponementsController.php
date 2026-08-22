<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherPostponementsController extends Controller
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
        $requests = $staffId === null
            ? []
            : $this->data->teacherPostponements(
                $staffId,
                app()->getLocale(),
                $organizationId,
            );

        // لا نعرض أزرار كتابة قبل أن يوفّر موديول Scheduling مساراتها الفعلية.
        $requests = array_values(array_filter(
            $requests,
            static fn (array $item): bool => $item['approveUrl'] !== ''
                && $item['proposeAlternativeUrl'] !== '',
        ));

        return Inertia::render('Teacher/Postponements', [
            'requests' => $requests,
            'statusColors' => $this->data->statusColors(),
        ]);
    }
}
