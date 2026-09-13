<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\DeactivateScheduleAction;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * معلمو الكورسات الفردية لطالب واحد، من صفحة ملفه.
 *
 * المدرسة تشتغل بالجداول الفردية: علاقة الطالب بمعلمه هي جدوله، لا انتساب
 * لمجموعة. لذلك «إسناد معلم» ينشئ جدولًا، و«تغيير المعلم» يعدّله، و«إزالة من
 * المعلم» يوقفه — وكلها تمر على أفعال الجدولة المعتمدة فتُعاد الحصص المستقبلية
 * أو تُلغى، وتبقى الحصص الماضية وحضورها ومستحقاتها كما هي.
 *
 * تغيير معلم حصة واحدة شيء آخر — ذاك «معلم بديل» له سجله وأثره على الأجر.
 *
 * Scheduling يملك نماذجه: الكونسول يستقبل مصفوفات بدائية فقط.
 */
final readonly class ConsoleIndividualTeacherService
{
    public function __construct(
        private CreateScheduleAction $create,
        private UpdateScheduleAction $update,
        private DeactivateScheduleAction $deactivate,
        private Transaction $transaction,
        private AcademicCatalogQueries $academics,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
        private AuditRecorder $audit,
    ) {}

    /**
     * الجداول الفردية السارية لهذا الطالب.
     *
     * @return list<array<string, mixed>>
     */
    public function forStudent(string $organizationId, string $studentProfileId): array
    {
        $schedules = $this->query($organizationId, $studentProfileId)->orderBy('created_at')->get();
        $courses = $this->academics->coursesByIds(
            $organizationId,
            array_values(array_unique($schedules->pluck('course_id')->map(strval(...))->all())),
        );
        $names = $this->staff->namesForProfiles(
            $organizationId,
            array_values(array_unique($schedules->pluck('staff_profile_id')->map(strval(...))->all())),
        );

        return $schedules->map(function (Schedule $schedule) use ($courses, $names): array {
            $course = $courses[(string) $schedule->course_id] ?? null;
            $rule = WeeklyRecurrence::fromRRule($schedule->rrule);
            $slots = $schedule->weeklySlots()->get()
                ->map(static fn ($slot): string => __('console_people.teaching.weekday_'.(int) $slot->weekday)
                    .' '.substr((string) $slot->start_time, 0, 5))
                ->values()->all();

            return [
                'id' => (string) $schedule->getKey(),
                'course_id' => (string) $schedule->course_id,
                'course' => $course === null
                    ? (string) $schedule->course_id
                    : ($course->name[app()->getLocale()] ?? $course->name['ar'] ?? $course->code),
                'teacher_id' => (string) $schedule->staff_profile_id,
                'teacher' => $names[(string) $schedule->staff_profile_id] ?? (string) $schedule->staff_profile_id,
                'slots' => $slots === []
                    ? [__('console_people.teaching.weekday_'.($rule->weekdays[0] ?? 0)).' '.substr($schedule->start_time, 0, 5)]
                    : $slots,
                'duration_minutes' => (int) $schedule->duration_minutes,
            ];
        })->values()->all();
    }

    /**
     * معرّفات الكورسات التي للطالب فيها جدول سارٍ.
     *
     * @return list<string>
     */
    public function scheduledCourseIds(string $organizationId, string $studentProfileId): array
    {
        return array_values(array_unique(
            $this->query($organizationId, $studentProfileId)->pluck('course_id')->map(strval(...))->all(),
        ));
    }

    /**
     * المعلمون المؤهلون لهذا الكورس والعاملون في المؤسسة.
     *
     * @return list<array{value: string, label: string}>
     */
    public function teacherOptions(string $organizationId, string $courseId): array
    {
        $qualified = array_values(array_filter(
            $this->qualifications->qualifiedTeacherIdsForCourse($courseId),
            fn (string $staffProfileId): bool => $this->staff->isActiveTeacherForOrganization($organizationId, $staffProfileId),
        ));
        $names = $this->staff->namesForProfiles($organizationId, $qualified);

        return array_values(array_map(
            static fn (string $id): array => ['value' => $id, 'label' => $names[$id] ?? $id],
            array_values(array_filter($qualified, static fn (string $id): bool => isset($names[$id]))),
        ));
    }

    /**
     * إسناد معلم لكورس فردي: جدول جديد تُولَّد منه الحصص القادمة.
     *
     * @param list<array{weekday: int, start_time: string}> $weeklySlots
     */
    public function assignTeacher(
        string $organizationId,
        string $studentProfileId,
        string $courseId,
        string $staffProfileId,
        array $weeklySlots,
        int $durationMinutes,
        int $intervalWeeks,
        string $timezone,
        string $startsOn,
        string $actorId,
        string $reason,
    ): string {
        Gate::authorize('create', Schedule::class);

        if ($this->query($organizationId, $studentProfileId)->where('course_id', $courseId)->exists()) {
            throw BusinessRuleViolation::make(
                'scheduling.course_already_scheduled',
                'scheduling::errors.course_already_scheduled',
            );
        }

        $schedule = $this->create->execute($organizationId, [
            'target_type' => 'student',
            'student_profile_id' => $studentProfileId,
            'group_id' => null,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'weekly_slots' => $weeklySlots,
            'weekdays' => array_column($weeklySlots, 'weekday'),
            'interval_weeks' => max(1, $intervalWeeks),
            'start_time' => $weeklySlots[0]['start_time'] ?? null,
            'duration_minutes' => $durationMinutes,
            'timezone' => $timezone,
            'starts_on' => $startsOn,
            'ends_on' => null,
        ], $actorId, $reason);

        return (string) $schedule->getKey();
    }

    /**
     * ربط الطالب بمعلمه قبل حسم الموعد: رابط تدريس معلق بلا جدول.
     *
     * يظهر الطالب في قوائم المعلم موسومًا بانتظار الجدول، ولا يولّد حصصًا ولا
     * استحقاقًا ماليًا، ويتنحّى تلقائيًا عند إنشاء جدول لنفس الطالب والكورس.
     */
    public function linkTeacher(
        string $organizationId,
        string $studentProfileId,
        string $courseId,
        string $staffProfileId,
        int $durationMinutes,
        string $actorId,
        string $reason,
    ): string {
        Gate::authorize('create', PendingTeachingAssignment::class);

        // نفس حراس الجدول: الرابط المعلق يصير جدولًا لاحقًا، فلا يُقبل ما لا يُجدول.
        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        if ($course === null) {
            throw BusinessRuleViolation::make(
                'scheduling.course_not_found',
                'scheduling::errors.course_not_found',
            );
        }

        if ($course->sessionMode === 'group') {
            throw BusinessRuleViolation::make(
                'scheduling.course_mode_mismatch',
                'scheduling::errors.course_mode_mismatch',
            );
        }

        if (!$this->staff->isActiveTeacherForOrganization($organizationId, $staffProfileId)
            || !$this->qualifications->isQualified($staffProfileId, $courseId)) {
            throw BusinessRuleViolation::make(
                'scheduling.teacher_not_eligible',
                'scheduling::errors.teacher_not_eligible',
            );
        }

        if ($this->query($organizationId, $studentProfileId)->where('course_id', $courseId)->exists()) {
            throw BusinessRuleViolation::make(
                'scheduling.course_already_scheduled',
                'scheduling::errors.course_already_scheduled',
            );
        }

        return $this->transaction->run(function () use (
            $organizationId, $studentProfileId, $courseId, $staffProfileId, $durationMinutes, $actorId, $reason,
        ): string {
            // القيد الفريد يشمل الصفوف المحذوفة منطقيًا، فالبحث معها ثم الاستعادة.
            $existing = PendingTeachingAssignment::query()
                ->withTrashed()
                ->where('organization_id', $organizationId)
                ->where('student_profile_id', $studentProfileId)
                ->where('staff_profile_id', $staffProfileId)
                ->where('course_id', $courseId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                return (string) $existing->getKey();
            }

            $link = PendingTeachingAssignment::query()->create([
                'organization_id' => $organizationId,
                'student_profile_id' => $studentProfileId,
                'staff_profile_id' => $staffProfileId,
                'course_id' => $courseId,
                'session_type' => 'individual',
                'duration_minutes' => $durationMinutes,
                'reason' => $reason,
                'created_by' => $actorId,
            ]);

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'scheduling.teaching_link_created',
                auditableType: 'pending_teaching_assignment',
                auditableId: (string) $link->getKey(),
                oldValues: null,
                newValues: [
                    'student_profile_id' => $studentProfileId,
                    'staff_profile_id' => $staffProfileId,
                    'course_id' => $courseId,
                    'duration_minutes' => $durationMinutes,
                ],
                reason: $reason,
            );

            return (string) $link->getKey();
        });
    }

    /** إزالة الطالب من معلمه: إيقاف الجدول وإلغاء حصصه القادمة. */
    public function removeTeacher(
        string $organizationId,
        string $studentProfileId,
        string $scheduleId,
        string $actorId,
        string $reason,
    ): void {
        $this->transaction->run(function () use ($organizationId, $studentProfileId, $scheduleId, $actorId, $reason): void {
            /** @var Schedule $schedule */
            $schedule = $this->query($organizationId, $studentProfileId)->lockForUpdate()->findOrFail($scheduleId);
            Gate::authorize('deactivate', $schedule);
            $this->deactivate->execute($schedule, $actorId, $reason);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function changeTeacher(
        string $organizationId,
        string $studentProfileId,
        string $scheduleId,
        string $staffProfileId,
        string $actorId,
        string $reason,
    ): array {
        return $this->transaction->run(function () use (
            $organizationId, $studentProfileId, $scheduleId, $staffProfileId, $actorId, $reason,
        ): array {
            /** @var Schedule $schedule */
            $schedule = $this->query($organizationId, $studentProfileId)->lockForUpdate()->findOrFail($scheduleId);
            Gate::authorize('update', $schedule);

            if ((string) $schedule->staff_profile_id === $staffProfileId) {
                throw BusinessRuleViolation::make(
                    'scheduling.teacher_unchanged',
                    'scheduling::errors.teacher_unchanged',
                );
            }

            $saved = $this->update->execute(
                $schedule,
                [...$this->definition($schedule), 'staff_profile_id' => $staffProfileId],
                $actorId,
                $reason,
            );

            return [
                'id' => (string) $saved->getKey(),
                'staff_profile_id' => (string) $saved->staff_profile_id,
            ];
        });
    }

    /**
     * تعريف الجدول الحالي كما يفهمه UpdateScheduleAction — يُعاد إرساله كما هو
     * ولا يتغير منه إلا المعلم، فلا يمس تغيير المعلم المواعيد ولا المدة.
     *
     * @return array<string, mixed>
     */
    private function definition(Schedule $schedule): array
    {
        $rule = WeeklyRecurrence::fromRRule($schedule->rrule);
        $slots = $schedule->weeklySlots()->get()->map(static fn ($slot): array => [
            'weekday' => (int) $slot->weekday,
            'start_time' => substr((string) $slot->start_time, 0, 5),
        ])->values()->all();

        if ($slots === []) {
            $slots = array_map(
                static fn (int $weekday): array => [
                    'weekday' => $weekday,
                    'start_time' => substr($schedule->start_time, 0, 5),
                ],
                $rule->weekdays,
            );
        }

        return [
            'target_type' => 'student',
            'student_profile_id' => (string) $schedule->student_profile_id,
            'group_id' => null,
            'course_id' => (string) $schedule->course_id,
            'weekly_slots' => $slots,
            'duration_minutes' => (int) $schedule->duration_minutes,
            'interval_weeks' => $rule->intervalWeeks,
            'timezone' => (string) $schedule->timezone,
            'starts_on' => $schedule->starts_on->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
        ];
    }

    /** @return Builder<Schedule> */
    private function query(string $organizationId, string $studentProfileId)
    {
        return Schedule::query()
            ->forOrganization($organizationId)
            ->where('student_profile_id', $studentProfileId)
            ->where('session_type', 'individual')
            ->where('is_active', true);
    }
}
