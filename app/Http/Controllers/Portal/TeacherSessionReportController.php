<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\SubmitTeacherSessionReportRequest;
use Illuminate\Http\RedirectResponse;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\Sessions\Application\Actions\RecordOffPlatformSessionAction;
use Modules\Sessions\Domain\Models\Session;

final class TeacherSessionReportController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly SubmitSessionReportAction $submit,
        private readonly RecordOffPlatformSessionAction $recordOffPlatform,
    ) {}

    public function __invoke(SubmitTeacherSessionReportRequest $request, string $session): RedirectResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $staffProfileId = $this->data->staffProfileId($actorId, $organizationId);

        abort_if($organizationId === '' || $staffProfileId === null, 403);
        $validated = $request->validated();

        /*
         * إقرار المعلم أن الحصة انعقدت فعليًا خارج المنصة — انظر تعليق
         * Api\TeacherSessionController::submitReport لنفس المنطق بالضبط.
         */
        if (($validated['held_off_platform'] ?? false) === true) {
            $sessionModel = Session::query()->forOrganization($organizationId)->findOrFail($session);

            // انظر Api\TeacherSessionController::submitReport — نفس تحقق
            // الملكية بالضبط ولنفس السبب.
            abort_if(
                !in_array($staffProfileId, [
                    $sessionModel->staff_profile_id,
                    $sessionModel->original_teacher_id,
                ], true),
                403,
            );

            $this->recordOffPlatform->execute($sessionModel, $actorId, (string) $validated['summary']);
        }

        $this->submit->executeForTeacher(
            organizationId: $organizationId,
            sessionId: $session,
            staffProfileId: $staffProfileId,
            actorId: $actorId,
            students: $validated['students'],
            topicsCovered: (string) $validated['summary'],
            generalNotes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return back()->with('success', __('academicreports::messages.session_report_submitted'));
    }
}
