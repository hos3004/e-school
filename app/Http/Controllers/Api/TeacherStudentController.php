<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * دليل طلاب المعلم للموبايل — يستخدم PortalData::teacherStudentRoster/
 * teacherStudentRosterProfile الجديدتين، وليس teacherStudentsDetailed/
 * teacherStudentProfile القديمتين: القديمتان تقرآن من المجموعات فقط، وهذه
 * المدرسة تعمل غالبًا بجداول فردية (انظر تعليق teacherStudentRoster).
 */
final class TeacherStudentController extends Controller
{
    public function __construct(private readonly PortalData $data) {}

    public function index(Request $request): JsonResponse
    {
        [$organizationId, $staffProfileId] = $this->actor($request);

        return response()->json([
            'students' => $staffProfileId === null
                ? []
                : $this->data->teacherStudentRoster($staffProfileId, $organizationId, app()->getLocale()),
        ]);
    }

    public function show(Request $request, string $student): JsonResponse
    {
        [$organizationId, $staffProfileId] = $this->actor($request);

        abort_if($staffProfileId === null, 404);

        $profile = $this->data->teacherStudentRosterProfile(
            $staffProfileId,
            $student,
            $organizationId,
            app()->getLocale(),
        );

        abort_if($profile === null, 404);

        return response()->json(['student' => $profile]);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function actor(Request $request): array
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        return [$organizationId, $staffProfileId];
    }
}
