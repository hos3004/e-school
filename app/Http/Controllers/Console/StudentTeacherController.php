<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Http\Requests\Console\AssignIndividualTeacherRequest;
use App\Http\Requests\Console\ChangeIndividualTeacherRequest;
use App\Http\Requests\Console\RemoveIndividualTeacherRequest;
use App\Http\Requests\Console\SetScheduleFlexibleStartRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Scheduling\Application\Services\ConsoleIndividualTeacherService;
use Modules\Staff\Application\Actions\SupersedeTeacherRate;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

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
        $slots = $request->weeklySlots();

        /*
         * السعر قبل الإسناد: المدة المخصّصة لا يقبلها حارس الجدولة بلا سعر
         * ساري للمعلم. المعاملة تضم الاثنين فلا يبقى سعر لإسناد لم يتم.
         */
        DB::transaction(function () use ($request, $student, $slots): void {
            $startsOn = (string) $request->validated('starts_on');
            $this->recordRate(
                $request,
                (string) $student->organization_id,
                (string) $request->validated('course_id'),
                (string) $request->validated('staff_profile_id'),
                $startsOn !== '' ? $startsOn : CarbonImmutable::now('UTC')->toDateString(),
            );

            if ($slots === []) {
                // موعد مؤجَّل: ربط الطالب بمعلمه بلا جدول بعد — لا حصص ولا
                // استحقاق مالي حتى يُضاف الموعد لاحقًا من نفس الصفحة.
                $this->schedules->linkTeacher(
                    organizationId: (string) $student->organization_id,
                    studentProfileId: (string) $student->getKey(),
                    courseId: (string) $request->validated('course_id'),
                    staffProfileId: (string) $request->validated('staff_profile_id'),
                    durationMinutes: (int) $request->validated('duration_minutes'),
                    actorId: (string) $request->user()?->getAuthIdentifier(),
                    reason: $request->reason(),
                );

                return;
            }

            $this->schedules->assignTeacher(
                organizationId: (string) $student->organization_id,
                studentProfileId: (string) $student->getKey(),
                courseId: (string) $request->validated('course_id'),
                staffProfileId: (string) $request->validated('staff_profile_id'),
                weeklySlots: $slots,
                durationMinutes: (int) $request->validated('duration_minutes'),
                intervalWeeks: (int) ($request->validated('interval_weeks') ?? 1),
                timezone: (string) $request->validated('timezone'),
                startsOn: (string) $request->validated('starts_on'),
                actorId: (string) $request->user()?->getAuthIdentifier(),
                reason: $request->reason(),
                flexibleStart: $request->boolean('flexible_start'),
            );
        });

        return back()->with('success', __($slots === [] ? 'console_people.teaching.linked' : 'console_people.teaching.assigned'));
    }

    public function flexibleStart(SetScheduleFlexibleStartRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);

        $this->schedules->setFlexibleStart(
            organizationId: (string) $student->organization_id,
            studentProfileId: (string) $student->getKey(),
            scheduleId: (string) $request->validated('schedule_id'),
            flexibleStart: $request->boolean('flexible_start'),
            actorId: (string) $request->user()?->getAuthIdentifier(),
            reason: $request->reason(),
        );

        return back()->with('success', __(
            $request->boolean('flexible_start')
                ? 'console_people.teaching.flexible_start_enabled'
                : 'console_people.teaching.flexible_start_disabled',
        ));
    }

    public function update(ChangeIndividualTeacherRequest $request, string $profile): RedirectResponse
    {
        $student = $this->student($request, $profile);
        $scheduleId = (string) $request->validated('schedule_id');

        DB::transaction(function () use ($request, $student, $scheduleId): void {
            // كورس الجدول يأتي من الجدول نفسه لا من العميل.
            $schedule = collect($this->schedules->forStudent(
                (string) $student->organization_id,
                (string) $student->getKey(),
            ))->firstWhere('id', $scheduleId);

            $this->recordRate(
                $request,
                (string) $student->organization_id,
                (string) ($schedule['course_id'] ?? ''),
                (string) $request->validated('staff_profile_id'),
                CarbonImmutable::now('UTC')->toDateString(),
            );

            $this->schedules->changeTeacher(
                organizationId: (string) $student->organization_id,
                studentProfileId: (string) $student->getKey(),
                scheduleId: $scheduleId,
                staffProfileId: (string) $request->validated('staff_profile_id'),
                actorId: (string) $request->user()?->getAuthIdentifier(),
                reason: $request->reason(),
            );
        });

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

    /**
     * سعر حصة المعلم في هذا الكورس، حين يُرسله المستخدم مع الإسناد أو التغيير.
     *
     * السعر المرسل مطابقًا للسعر الساري لا يُعاد تسجيله: الحقل يصل معبّأً
     * بالسعر الحالي، فحفظ الجدول وحده كان سينشئ سطرًا جديدًا بلا تغيير.
     */
    private function recordRate(
        AssignIndividualTeacherRequest|ChangeIndividualTeacherRequest $request,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        string $effectiveFrom,
    ): void {
        $amount = $request->validated('session_rate_major');

        if ($amount === null || (string) $amount === '') {
            return;
        }

        $course = $courseId === ''
            ? null
            : (app(AcademicCatalogQueries::class)->coursesByIds($organizationId, [$courseId])[$courseId] ?? null);

        if ($course === null || $course->programId === null) {
            throw ValidationException::withMessages([
                'session_rate_major' => __('console_people.rates.course_unavailable'),
            ]);
        }

        $requested = number_format((float) $amount, 2, '.', '');
        $current = app(TeacherRateResolver::class)->resolve(
            $staffProfileId,
            CarbonImmutable::parse($effectiveFrom, 'UTC'),
            $course->programId,
            $courseId,
            'individual',
        );

        if ($current !== null
            && $current['scope'] === RateScope::Course
            && number_format((float) $current['money']->toMajor(), 2, '.', '') === $requested) {
            return;
        }

        try {
            app(SupersedeTeacherRate::class)->execute(
                staffProfileId: $staffProfileId,
                scope: RateScope::Course,
                amountMajor: $requested,
                effectiveFrom: $effectiveFrom,
                programId: $course->programId,
                courseId: $courseId,
                actorId: (string) $request->user()?->getAuthIdentifier(),
                reason: $request->reason(),
            );
        } catch (BusinessRuleViolation $violation) {
            throw ValidationException::withMessages([
                'session_rate_major' => $violation->getMessage(),
            ]);
        }
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
