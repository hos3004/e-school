<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\AssignIndividualTeacherRequest;
use App\Http\Requests\Console\ChangeIndividualTeacherRequest;
use App\Http\Requests\Console\RemoveIndividualTeacherRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Application\Services\ConsoleIndividualTeacherService;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * معلمو الكورسات الفردية للطالب: إسناد وتغيير وإزالة، من صفحة ملفه.
 *
 * كلها تمر على أفعال الجدولة المعتمدة، فتُولَّد الحصص القادمة للمعلم الجديد
 * أو تُلغى عند الإزالة، بلا مساس بالحصص الماضية ومستحقاتها.
 */
final class StudentTeacherController extends Controller
{
    public function __construct(
        private readonly ConsoleIndividualTeacherService $schedules,
    ) {}

    public function options(Request $request, string $profile): JsonResponse
    {
        $student = $this->student($request, $profile);
        $input = $request->validate(['course_id' => ['required', 'ulid']]);

        return response()->json([
            'teachers' => $this->schedules->teacherOptions(
                (string) $student->organization_id,
                (string) $input['course_id'],
            ),
        ]);
    }

    public function store(AssignIndividualTeacherRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);

        $this->schedules->assignTeacher(
            organizationId: (string) $student->organization_id,
            studentProfileId: (string) $student->getKey(),
            courseId: (string) $request->validated('course_id'),
            staffProfileId: (string) $request->validated('staff_profile_id'),
            weeklySlots: $request->weeklySlots(),
            durationMinutes: (int) $request->validated('duration_minutes'),
            intervalWeeks: (int) ($request->validated('interval_weeks') ?? 1),
            timezone: (string) $request->validated('timezone'),
            startsOn: (string) $request->validated('starts_on'),
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.teaching.assigned'));
    }

    public function update(ChangeIndividualTeacherRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);

        $this->schedules->changeTeacher(
            organizationId: (string) $student->organization_id,
            studentProfileId: (string) $student->getKey(),
            scheduleId: (string) $request->validated('schedule_id'),
            staffProfileId: (string) $request->validated('staff_profile_id'),
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.teaching.changed'));
    }

    public function destroy(RemoveIndividualTeacherRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);

        $this->schedules->removeTeacher(
            organizationId: (string) $student->organization_id,
            studentProfileId: (string) $student->getKey(),
            scheduleId: (string) $request->validated('schedule_id'),
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: $request->reason(),
        );

        return back()->with('success', __('console_people.teaching.removed'));
    }

    private function student(Request $request, string $profile): StudentProfile
    {
        $organizationId = (string) data_get($request->user(), 'organization_id');
        abort_if($organizationId === '', 403);

        /** @var StudentProfile $student */
        $student = StudentProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('view', $student);

        return $student;
    }
}
