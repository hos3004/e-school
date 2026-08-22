<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Enums;

/**
 * أنواع المخالفات — مفاتيحها مطابقة لمصفوفة config('discipline.countable_events')
 * حتى يقرأ المحرّك من الإعدادات ولا يُشفِّر أي سياسة في الكود.
 */
enum ViolationType: string
{
    /** حجز الحصة ولم يحضر ولم يُخطر. */
    case NoShow = 'no_show';

    /** غياب بدون عذر مقبول. */
    case UnexcusedAbsence = 'unexcused_absence';

    /** إلغاء بعد انقضاء مهلة الإلغاء المسموحة. */
    case LateCancellation = 'late_cancellation';

    /** غياب بعذر مقبول — لا يُحتسب افتراضيًا. */
    case ExcusedAbsence = 'excused_absence';

    /** تأجيل موافق عليه — لا يُحتسب افتراضيًا. */
    case ApprovedPostponement = 'approved_postponement';

    /** غياب المعلم لا يُحمَّل على الطالب — لا يُحتسب افتراضيًا. */
    case TeacherAbsence = 'teacher_absence';

    /**
     * هل يُحتسب هذا النوع مخالفةً؟ القرار من config وليس من الكود.
     */
    public function isCountable(): bool
    {
        $countable = (array) config('discipline.countable_events', []);

        return (bool) ($countable[$this->value] ?? false);
    }

    public function label(): string
    {
        return __('discipline::violations.types.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NoShow, self::UnexcusedAbsence => 'danger',
            self::LateCancellation => 'warning',
            self::ExcusedAbsence => 'info',
            self::ApprovedPostponement => 'gray',
            self::TeacherAbsence => 'secondary',
        };
    }
}
