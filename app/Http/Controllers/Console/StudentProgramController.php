<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\EnrollStudentInProgramRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * إضافة الطالب إلى برنامج آخر من صفحة ملفه.
 *
 * القيد في البرنامج لا يحتاج مجموعة: المدرسة تشتغل بالكورسات الفردية، وعلاقة
 * الطالب بمعلمه جدولٌ يُسنَد بعد القيد. لذلك الخطوة هنا قيد فقط، ثم يُسنَد
 * المعلم من قسم المعلمين في نفس الصفحة.
 */
final class StudentProgramController extends Controller
{
    public function __construct(
        private readonly AcademicCatalogQueries $catalog,
        private readonly EnrollmentPlacementGateway $enrollments,
    ) {}

    public function store(EnrollStudentInProgramRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);
        $organizationId = (string) $student->organization_id;
        $programId = (string) $request->validated('program_id');

        if (!isset($this->catalog->programsByIds($organizationId, [$programId])[$programId])) {
            throw ValidationException::withMessages([
                'program_id' => __('console_people.programs.program_unavailable'),
            ]);
        }

        $this->enrollments->activate(
            organizationId: $organizationId,
            studentProfileId: (string) $student->getKey(),
            programId: $programId,
            reason: $request->reason(),
            actorId: (string) $request->user()?->getAuthIdentifier(),
        );

        return back()->with('success', __('console_people.programs.enrolled'));
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
