<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Shared\ValueObjects\TimeRange;

/**
 * ترشيح المعلمين البدلاء لحصة.
 *
 * الترتيب مقصود: نعرض أولًا المؤهل للمادة والنشط والمتاح وغير المرتبط بحصة
 * أخرى في نفس الوقت. المشرف يستطيع تجاوز الشروط بصلاحية خاصة، لكن التجاوز
 * يُسجَّل بسببه في session_substitutions ولا يمر صامتًا.
 *
 * بيانات الموظفين والتأهيلات والإجازات تعبر من العقود العامة لموديول Staff؛
 * موديول Sessions لا يعرف جداول الموديولات الأخرى.
 */
final readonly class SubstituteCandidateFinder
{
    public function __construct(
        private StaffQueries $staff,
        private TeacherQualificationQueries $qualifications,
    ) {}

    /**
     * الحالات التي لا تحجز وقت المعلم فعليًا.
     *
     * @var list<string>
     */
    private const RELEASED_STATUSES = [
        'cancelled_by_student',
        'cancelled_by_teacher',
        'cancelled_by_school',
        'postponed',
    ];

    /**
     * مرشحون مرتّبون: المؤهلون والمتاحون أولًا.
     *
     * @return list<array{
     *     staff_profile_id: string,
     *     name: string,
     *     staff_code: string,
     *     is_qualified: bool,
     *     is_available: bool,
     *     is_on_leave: bool,
     *     conflicting_sessions: int
     * }>
     */
    public function candidatesFor(string $sessionId, bool $includeUnqualified = false): array
    {
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->first(['id', 'organization_id', 'course_id', 'staff_profile_id', 'scheduled_start', 'scheduled_end']);

        if ($session === null) {
            return [];
        }

        $range = new TimeRange(
            CarbonImmutable::parse((string) $session->scheduled_start),
            CarbonImmutable::parse((string) $session->scheduled_end),
        );

        $qualified = $this->qualifiedForCourse(
            $session->course_id === null ? null : (string) $session->course_id,
        );

        $pool = $includeUnqualified
            ? $this->activeTeachers((string) $session->organization_id)
            : array_values(array_filter(
                $this->activeTeachers((string) $session->organization_id),
                static fn (array $t): bool => in_array($t['staff_profile_id'], $qualified, true),
            ));

        $candidates = [];

        foreach ($pool as $teacher) {
            // المعلم المسند حاليًا ليس مرشحًا لاستبدال نفسه.
            if ($teacher['staff_profile_id'] === (string) $session->staff_profile_id) {
                continue;
            }

            $conflicts = $this->conflictCount($teacher['staff_profile_id'], $range, $sessionId);
            $onLeave = $this->isOnLeave($teacher['staff_profile_id'], $range);

            $candidates[] = [
                'staff_profile_id' => $teacher['staff_profile_id'],
                'name' => $teacher['name'],
                'staff_code' => $teacher['staff_code'],
                'is_qualified' => in_array($teacher['staff_profile_id'], $qualified, true),
                'is_available' => $conflicts === 0 && !$onLeave,
                'is_on_leave' => $onLeave,
                'conflicting_sessions' => $conflicts,
            ];
        }

        // المتاح المؤهل أولًا، ثم المؤهل غير المتاح، ثم الباقي.
        usort($candidates, static function (array $a, array $b): int {
            return [$b['is_qualified'], $b['is_available'], $a['name']]
                <=> [$a['is_qualified'], $a['is_available'], $b['name']];
        });

        return $candidates;
    }

    /**
     * هل يصلح هذا المعلم بديلًا دون تجاوز إداري؟
     *
     * @return array{qualified: bool, available: bool, on_leave: bool, conflicts: int}
     */
    public function evaluate(string $sessionId, string $staffProfileId): array
    {
        $session = DB::table('sessions')
            ->where('id', $sessionId)
            ->first(['organization_id', 'course_id', 'scheduled_start', 'scheduled_end']);

        if ($session === null) {
            return ['qualified' => false, 'available' => false, 'on_leave' => false, 'conflicts' => 0];
        }

        $range = new TimeRange(
            CarbonImmutable::parse((string) $session->scheduled_start),
            CarbonImmutable::parse((string) $session->scheduled_end),
        );

        $qualified = in_array(
            $staffProfileId,
            $this->qualifiedForCourse(
                $session->course_id === null ? null : (string) $session->course_id,
            ),
            true,
        );

        $conflicts = $this->conflictCount($staffProfileId, $range, $sessionId);
        $onLeave = $this->isOnLeave($staffProfileId, $range);

        return [
            'qualified' => $qualified,
            'available' => $conflicts === 0 && !$onLeave,
            'on_leave' => $onLeave,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return list<string>
     */
    private function qualifiedForCourse(?string $courseId): array
    {
        if ($courseId === null) {
            return [];
        }

        return $this->qualifications->qualifiedTeacherIdsForCourse($courseId);
    }

    /**
     * @return list<array{staff_profile_id: string, name: string, staff_code: string}>
     */
    private function activeTeachers(string $organizationId): array
    {
        return $this->staff->activeTeacherSummariesForOrganization($organizationId);
    }

    private function conflictCount(string $staffProfileId, TimeRange $range, string $ignoreSessionId): int
    {
        return DB::table('sessions')
            ->whereNull('deleted_at')
            ->where('id', '!=', $ignoreSessionId)
            ->where('staff_profile_id', $staffProfileId)
            ->whereNotIn('status', self::RELEASED_STATUSES)
            // تداخل حقيقي على فترة نصف مفتوحة [start, end)
            ->where('scheduled_start', '<', $range->end)
            ->where('scheduled_end', '>', $range->start)
            ->count();
    }

    private function isOnLeave(string $staffProfileId, TimeRange $range): bool
    {
        return $this->staff->isOnApprovedLeave($staffProfileId, $range->start, $range->end);
    }
}
