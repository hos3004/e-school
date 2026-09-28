<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * جدول وتفاصيل حصص الطالب للموبايل — مرآة Portal\StudentDashboardController/
 * StudentScheduleController/StudentSessionController، نفس PortalData بالضبط.
 */
final class StudentSessionController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function index(Request $request): JsonResponse
    {
        [$organizationId, $studentProfileId] = $this->actor($request);

        return response()->json([
            'sessions' => $studentProfileId === null
                ? []
                : $this->data->upcomingStudentSessions($studentProfileId, app()->getLocale(), $organizationId),
        ]);
    }

    public function show(Request $request, string $session): JsonResponse
    {
        [$organizationId, $studentProfileId] = $this->actor($request);

        abort_if($studentProfileId === null, 404);

        $data = $this->data->studentSession($studentProfileId, $session, app()->getLocale(), $organizationId);

        abort_if($data === null, 404);

        $data['postponementRequest'] = $this->data->postponementForSession(
            $session,
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );
        $data['studentApology'] = $this->data->studentApologyForSession($session, $studentProfileId, $organizationId);
        $data['canRequestPostponement'] = (bool) $request->user()?->can('session.postpone.request')
            && in_array((string) $data['status'], ['scheduled', 'confirmed'], true);
        $data['canSubmitApology'] = $data['canRequestPostponement'];

        return response()->json(['session' => $data]);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function actor(Request $request): array
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $studentProfileId = $this->data->studentProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        return [$organizationId, $studentProfileId];
    }
}
