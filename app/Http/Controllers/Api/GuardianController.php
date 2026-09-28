<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * لوحة ولي الأمر للموبايل — مرآة Portal\GuardianDashboardController/
 * GuardianChildController، نفس PortalData::guardianChildren/guardianChild
 * بالضبط. كل استعلام طفل يتحقق أولًا أن الطفل فعلًا ضمن أطفال ولي الأمر
 * الحالي — guardianChild() ترجع null غير ذلك، فلا نحتاج فحصًا منفصلًا.
 */
final class GuardianController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function children(Request $request): JsonResponse
    {
        [$organizationId, $userId] = $this->actor($request);

        return response()->json([
            'children' => $this->data->guardianChildren($userId, app()->getLocale(), $organizationId),
        ]);
    }

    public function childSessions(Request $request, string $child): JsonResponse
    {
        [$organizationId, $userId] = $this->actor($request);
        $locale = app()->getLocale();

        $childProfile = $this->data->guardianChild($userId, $child, $locale, $organizationId);
        abort_if($childProfile === null, 404);

        return response()->json([
            'child' => $childProfile,
            'sessions' => $this->data->upcomingStudentSessions($child, $locale, $organizationId),
        ]);
    }

    public function childSession(Request $request, string $child, string $session): JsonResponse
    {
        [$organizationId, $userId] = $this->actor($request);
        $locale = app()->getLocale();

        $childProfile = $this->data->guardianChild($userId, $child, $locale, $organizationId);
        abort_if($childProfile === null, 404);

        $data = $this->data->studentSession($child, $session, $locale, $organizationId);
        abort_if($data === null, 404);

        return response()->json(['session' => $data]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function actor(Request $request): array
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $userId = (string) $request->user()?->getAuthIdentifier();

        return [$organizationId, $userId];
    }
}
