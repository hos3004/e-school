<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Services;

use Illuminate\Support\Facades\DB;
use Shared\ValueObjects\TimeRange;

/**
 * كشف تعارض المواعيد قبل الحفظ.
 *
 * الفحص هنا طبقة أولى تعطي رسالة مفهومة للمستخدم؛ الطبقة الحاسمة قيد
 * EXCLUDE على جدول sessions، وهو ما يجعل التعارض مستحيلًا حتى مع طلبين
 * متزامنين. لا نكتفي بأحدهما.
 *
 * نستعلم عن جدول sessions باسمه لا عبر نموذج موديول Sessions — استيراد
 * نموذج عبر حدود الموديولات ممنوع في هذا المشروع.
 */
final readonly class ConflictDetector
{
    /**
     * الحالات التي لا تحجز وقتًا فعليًا، فلا تُحتسب تعارضًا.
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
     * معرّفات الحصص المتعارضة مع المدى المعطى.
     *
     * @return list<string>
     */
    public function conflictsFor(
        TimeRange $range,
        ?string $staffProfileId = null,
        ?string $studentProfileId = null,
        ?string $groupId = null,
        ?string $ignoreSessionId = null,
    ): array {
        if ($staffProfileId === null && $studentProfileId === null && $groupId === null) {
            return [];
        }

        $query = DB::table('sessions')
            ->whereNull('deleted_at')
            ->whereNotIn('status', self::RELEASED_STATUSES)
            // التداخل الحقيقي: [start, end) — حصة تنتهي 18:00 وأخرى تبدأ 18:00 لا تتعارضان.
            ->where('scheduled_start', '<', $range->end)
            ->where('scheduled_end', '>', $range->start);

        if ($ignoreSessionId !== null) {
            $query->where('id', '!=', $ignoreSessionId);
        }

        $query->where(function ($q) use ($staffProfileId, $studentProfileId, $groupId): void {
            if ($staffProfileId !== null) {
                $q->orWhere('staff_profile_id', $staffProfileId);
            }

            if ($groupId !== null) {
                $q->orWhere('group_id', $groupId);
            }

            if ($studentProfileId !== null) {
                $q->orWhereIn('id', DB::table('session_participants')
                    ->select('session_id')
                    ->where('student_profile_id', $studentProfileId));
            }
        });

        return array_map(
            static fn (object $row): string => (string) $row->id,
            $query->get(['id'])->all(),
        );
    }

    public function hasConflict(
        TimeRange $range,
        ?string $staffProfileId = null,
        ?string $studentProfileId = null,
        ?string $groupId = null,
        ?string $ignoreSessionId = null,
    ): bool {
        return $this->conflictsFor(
            $range,
            $staffProfileId,
            $studentProfileId,
            $groupId,
            $ignoreSessionId,
        ) !== [];
    }

    /**
     * هل يقع المدى داخل إتاحة المعلم المعلنة؟
     *
     * غياب أي إتاحة معلنة يعني "متاح دائمًا" — لا نمنع الجدولة على معلم
     * لم يملأ جدول إتاحته بعد.
     */
    public function isWithinTeacherAvailability(TimeRange $range, string $staffProfileId): bool
    {
        $declared = DB::table('teacher_availability')
            ->where('staff_profile_id', $staffProfileId)
            ->count();

        if ($declared === 0) {
            return true;
        }

        $weekday = (int) $range->start->dayOfWeek;

        return DB::table('teacher_availability')
            ->where('staff_profile_id', $staffProfileId)
            ->where('weekday', $weekday)
            ->where('start_time', '<=', $range->start->format('H:i:s'))
            ->where('end_time', '>=', $range->end->format('H:i:s'))
            ->exists();
    }

    /**
     * هل المعلم في إجازة معتمدة خلال هذا المدى؟
     */
    public function isTeacherOnLeave(TimeRange $range, string $staffProfileId): bool
    {
        return DB::table('teacher_leaves')
            ->where('staff_profile_id', $staffProfileId)
            ->where('status', 'approved')
            ->where('starts_at', '<', $range->end)
            ->where('ends_at', '>', $range->start)
            ->exists();
    }
}
