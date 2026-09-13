<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تغيير معلم الكورس الفردي لطالب من صفحة ملفه.
 *
 * لا يعدّل حصصًا مباشرة: يمرّ عبر UpdateScheduleAction فتُلغى الحصص المستقبلية
 * خارج نافذة القفل وتُعاد بالمعلم الجديد. لذلك يخرج الطالب من جدول المعلم
 * السابق ويظهر عند الجديد بالشكل المعتمد، وتبقى الحصص الماضية وحضورها
 * ومستحقاتها كما هي.
 *
 * تغيير المعلم على حصة واحدة شيء آخر تمامًا — ذاك «معلم بديل» له سجله
 * وأثره على الأجر، ولا يُستخدم هنا.
 *
 * Scheduling يملك نماذجه: الكونسول يستقبل مصفوفات بدائية فقط.
 */
final readonly class ConsoleIndividualTeacherService
{
    public function __construct(
        private UpdateScheduleAction $update,
        private Transaction $transaction,
        private AcademicCatalogQueries $academics,
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
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

            return [
                'id' => (string) $schedule->getKey(),
                'course_id' => (string) $schedule->course_id,
                'course' => $course === null
                    ? (string) $schedule->course_id
                    : ($course->name[app()->getLocale()] ?? $course->name['ar'] ?? $course->code),
                'teacher_id' => (string) $schedule->staff_profile_id,
                'teacher' => $names[(string) $schedule->staff_profile_id] ?? (string) $schedule->staff_profile_id,
            ];
        })->values()->all();
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
