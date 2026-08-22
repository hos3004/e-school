<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Enums;

/**
 * حالة حضور الطالب في حصة.
 *
 * ليست present / absent فقط — لأن على التمييز بينها يترتب:
 * هل تُحتسب مخالفة؟ وهل يُخصم من المعلم؟ وهل تُخصم حصة من رصيد الطالب؟
 *
 * تُحسب مبدئيًا من أحداث الدخول والخروج (config/academic.php attendance)
 * ثم يعتمدها المعلم. أي تعديل بعد الاعتماد يُسجَّل في سجل التدقيق بسبب مكتوب.
 */
enum AttendanceStatus: string
{
    /** حضر المدة المطلوبة أو أكثر. */
    case Present = 'present';

    /** حضر لكنه دخل متأخرًا عن الحد المسموح. */
    case Late = 'late';

    /** حضر جزءًا من الحصة فقط. */
    case Partial = 'partial';

    /** انصرف قبل نهاية الحصة بأكثر من الحد المسموح. */
    case LeftEarly = 'left_early';

    /** غاب بعذر مقبول. */
    case Excused = 'excused';

    /** غاب بدون عذر — تُحتسب مخالفة. */
    case Absent = 'absent';

    /** لم يدخل الفصل إطلاقًا ولم يُخطر — تُحتسب مخالفة. */
    case NoShow = 'no_show';

    /** تعذّر الحضور لعطل تقني موثّق — لا تُحتسب مخالفة. */
    case TechnicalIssue = 'technical_issue';

    /** الحصة نفسها لم تُعقد (إلغاء أو تأجيل) — لا تُحتسب على أحد. */
    case NotHeld = 'not_held';

    /** هل يُعتبر حاضرًا لأغراض التقارير؟ */
    public function isPresent(): bool
    {
        return in_array($this, [self::Present, self::Late, self::Partial, self::LeftEarly], true);
    }

    /** هل تُحتسب مخالفة في محرّك الانضباط؟ */
    public function isViolation(): bool
    {
        return in_array($this, [self::Absent, self::NoShow], true);
    }

    /** هل يستحق المعلم أجر الحصة رغم غياب الطالب؟ */
    public function teacherStillEarns(): bool
    {
        return $this !== self::NotHeld;
    }

    /** مفتاح الحدث في config/discipline.php countable_events. */
    public function disciplineEventKey(): ?string
    {
        return match ($this) {
            self::Absent => 'unexcused_absence',
            self::NoShow => 'no_show',
            self::Excused => 'excused_absence',
            default => null,
        };
    }

    /**
     * استنباط الحالة من الدقائق الفعلية داخل الفصل.
     * النتيجة اقتراح للمعلم وليست قرارًا نهائيًا.
     */
    public static function deriveFromMinutes(
        int $attendedMinutes,
        int $sessionMinutes,
        int $joinedAfterMinutes,
        int $leftBeforeMinutes,
    ): self {
        $thresholds = config('academic.attendance.thresholds');

        if ($attendedMinutes === 0) {
            return self::NoShow;
        }

        $percent = (int) round(($attendedMinutes / max($sessionMinutes, 1)) * 100);

        if ($percent < $thresholds['partial_min_percent']) {
            return self::Absent;
        }

        if ($percent < $thresholds['present_min_percent']) {
            return self::Partial;
        }

        if ($leftBeforeMinutes >= $thresholds['left_early_before_minutes']) {
            return self::LeftEarly;
        }

        if ($joinedAfterMinutes >= $thresholds['late_after_minutes']) {
            return self::Late;
        }

        return self::Present;
    }

    public function label(): string
    {
        return __('attendance::status.'.$this->value);
    }
}
