<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ChangeIndividualTeacherRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Application\Services\ConsoleIndividualTeacherService;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * تغيير معلم الكورس الفردي من صفحة الطالب.
 *
 * التغيير يمر على مسار تعديل الجدول المعتمد، فتُعاد الحصص المستقبلية بالمعلم
 * الجديد ويخرج الطالب من جدول المعلم السابق، بلا مساس بالماضي ومستحقاته.
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

        return back()->with('success', __('console_people.teacher_change.saved'));
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
