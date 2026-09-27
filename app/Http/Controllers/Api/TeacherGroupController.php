<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مجموعات المعلم للموبايل — مرآة Portal\TeacherGroupsController/
 * TeacherGroupController بالضبط: PortalData::teacherGroupsDetailed/
 * teacherGroupDetailed غير معدَّلتين، وهنا القراءة قائمة على المجموعات
 * وحدها عمدًا (بخلاف دليل الطلاب) — هذه شاشة مجموعات فعلًا، لا دليل شامل.
 */
final class TeacherGroupController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        return response()->json([
            'groups' => $staffProfileId === null
                ? []
                : $this->data->teacherGroupsDetailed($staffProfileId, $organizationId, app()->getLocale()),
        ]);
    }

    public function show(Request $request, string $group): JsonResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        abort_if($staffProfileId === null, 404);

        $details = $this->data->teacherGroupDetailed(
            $staffProfileId,
            $group,
            $organizationId,
            app()->getLocale(),
        );

        abort_if($details === null, 404);

        return response()->json(['group' => $details]);
    }
}
