<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * بنود مطلوبة من المعلم للموبايل — حاليًا تقارير الحصص التي مضى موعدها
 * بلا تقرير (PortalData::teacherLateReportSessions، نفس الاستعلام الذي
 * تعتمد عليه Portal\TeacherDashboardController تمامًا، غير معدَّل هنا).
 *
 * حقل `type` مضاف هنا فقط (لا في PortalData) تحسّبًا لبنود مطلوبة أخرى
 * غير تقارير الحصص مستقبلًا (استبيانات مثلًا) — إضافة نوع جديد لاحقًا
 * تعني endpoint أو مصدر بيانات جديد يُدمج هنا، لا إعادة كتابة هذا العقد.
 */
final class TeacherRequiredReportsController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        if ($staffProfileId === null) {
            return response()->json(['items' => []]);
        }

        $sessions = $this->data->teacherLateReportSessions(
            $staffProfileId,
            app()->getLocale(),
            $organizationId,
        );

        return response()->json([
            'items' => array_map(
                static fn (array $session): array => $session + ['type' => 'session_report'],
                $sessions,
            ),
        ]);
    }
}
