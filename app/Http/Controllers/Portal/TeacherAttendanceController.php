<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\RecordTeacherAttendanceRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Attendance\Application\Actions\RecordAttendanceSheetAction;

/**
 * حفظ كشف حضور الحصة من بوابة المعلم.
 *
 * كانت الصفحة ترسل الكشف بـ PUT إلى `api/sessions/{session}/attendance` — وهي
 * مسار POST يرصد دخول مشارك واحد ويعيد JSON. النتيجة 405، ولو صُحّح الفعل
 * لبقي الشكل والدلالة مختلفَين. هذا المسار هو ما تحتاجه الصفحة فعلًا.
 */
final class TeacherAttendanceController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RecordAttendanceSheetAction $sheet,
    ) {}

    public function __invoke(RecordTeacherAttendanceRequest $request, string $session): RedirectResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $staffProfileId = $this->data->staffProfileId($actorId, $organizationId);

        abort_if($organizationId === '' || $staffProfileId === null, 403);

        $validated = $request->validated();

        $this->sheet->execute(
            organizationId: $organizationId,
            sessionId: $session,
            statuses: $validated['statuses'],
            actorId: $actorId,
            reason: isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        return back()->with('success', __('attendance::messages.sheet_saved'));
    }
}
