<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\RecordTeacherAttendanceRequest;
use App\Http\Requests\Portal\SubmitTeacherSessionReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\Attendance\Application\Actions\RecordAttendanceSheetAction;

/**
 * تفاصيل حصة المعلم للموبايل — نفس بيانات ومنطق بوابة الويب Portal بالضبط
 * (PortalData::teacherSession/teacherAttendance، RecordAttendanceSheetAction،
 * SubmitSessionReportAction::executeForTeacher)، فقط JSON بدل Inertia/redirect.
 */
final class TeacherSessionController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RecordAttendanceSheetAction $attendanceSheet,
        private readonly SubmitSessionReportAction $submitReport,
    ) {}

    public function show(Request $request, string $session): JsonResponse
    {
        [$organizationId, $staffProfileId] = $this->actor($request);

        $data = $this->data->teacherSession($staffProfileId, $session, app()->getLocale(), $organizationId);
        abort_if($data === null, 404);

        return response()->json([
            'session' => $data,
            'attendance' => $this->data->teacherAttendance($session, $organizationId),
            'attendance_statuses' => $this->attendanceStatusOptions(),
            'status_colors' => $this->data->statusColors(),
            'existing_report' => $this->data->teacherInitialReport($session, $staffProfileId),
        ]);
    }

    public function recordAttendance(RecordTeacherAttendanceRequest $request, string $session): JsonResponse
    {
        [$organizationId, $staffProfileId] = $this->actor($request);
        $validated = $request->validated();

        $tally = $this->attendanceSheet->execute(
            organizationId: $organizationId,
            sessionId: $session,
            staffProfileId: $staffProfileId,
            statuses: $validated['statuses'],
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        return response()->json(['tally' => $tally]);
    }

    public function submitReport(SubmitTeacherSessionReportRequest $request, string $session): JsonResponse
    {
        [$organizationId, $staffProfileId] = $this->actor($request);
        $validated = $request->validated();

        $this->submitReport->executeForTeacher(
            organizationId: $organizationId,
            sessionId: $session,
            staffProfileId: $staffProfileId,
            actorId: (string) $request->user()?->getAuthIdentifier(),
            students: $validated['students'],
            topicsCovered: (string) $validated['summary'],
            generalNotes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return response()->json(['status' => 'submitted']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function actor(Request $request): array
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $organizationId === ''
            ? null
            : $this->data->staffProfileId((string) $request->user()?->getAuthIdentifier(), $organizationId);

        abort_if($organizationId === '' || $staffProfileId === null, 403);

        return [$organizationId, $staffProfileId];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function attendanceStatusOptions(): array
    {
        return array_map(
            static fn (string $value): array => [
                'value' => $value,
                'label' => __('attendance::status.'.$value),
            ],
            $this->data->attendanceStatuses(),
        );
    }
}
