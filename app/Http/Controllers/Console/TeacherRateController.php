<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\TeacherRateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Staff\Application\Actions\SupersedeTeacherRate;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;

/**
 * سعر حصة المعلم من صفحة ملفه في الكونسول.
 *
 * كان السعر يُدخَل عند إنشاء الملف وحده، فلا سبيل لتغييره بعدها من أي شاشة.
 * السعر هنا زمني: يبدأ الجديد من تاريخه ويُقفل السابق عنده، فتبقى الحصص
 * الماضية وقيودها بسعرها الأصلي.
 */
final class TeacherRateController extends Controller
{
    public function store(
        TeacherRateRequest $request,
        string $profile,
        SupersedeTeacherRate $action,
        AcademicCatalogQueries $academics,
    ): RedirectResponse {
        /** @var StaffProfile $record */
        $record = StaffProfile::query()
            ->forOrganization((string) $request->user()?->organization_id)
            ->whereKey($profile)
            ->firstOrFail();
        Gate::authorize('update', $record);

        $scope = $request->scope();
        $organizationId = (string) $record->organization_id;
        $courseId = $scope === RateScope::Course ? (string) $request->validated('course_id') : null;
        $programId = $scope === RateScope::Program ? (string) $request->validated('program_id') : null;

        if ($courseId !== null) {
            // البرنامج يتبع الكورس؛ حلّ السعر يبحث بالاثنين معًا.
            $course = $academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

            if ($course === null || $course->programId === null) {
                throw ValidationException::withMessages([
                    'course_id' => __('console_people.rates.course_unavailable'),
                ]);
            }

            $programId = $course->programId;
        }

        if ($scope === RateScope::Program
            && !array_key_exists((string) $programId, $academics->programsByIds($organizationId, [(string) $programId]))) {
            throw ValidationException::withMessages([
                'program_id' => __('console_people.rates.program_unavailable'),
            ]);
        }

        try {
            $action->execute(
                staffProfileId: (string) $record->getKey(),
                scope: $scope,
                amountMajor: (string) $request->validated('amount_major'),
                effectiveFrom: (string) $request->validated('effective_from'),
                programId: $programId,
                courseId: $courseId,
                sessionType: $scope === RateScope::SessionType ? (string) $request->validated('session_type') : null,
                actorId: (string) $request->user()?->getAuthIdentifier(),
                reason: $request->reason(),
            );
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages(['amount_major' => $violation->getMessage()]);
        }

        return back()->with('success', __('console_people.rates.saved'));
    }
}
