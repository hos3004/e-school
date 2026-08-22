<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

/**
 * أنواع الاختبارات في المنصة.
 *
 * النوع يحدد الغرض والسلوك:
 *  - quiz         : اختبار قصير ضمن مسار المادة.
 *  - exam         : اختبار نهائي للمادة.
 *  - placement    : اختبار تحديد مستقبل عند الانضمام.
 *  - reactivation : اختبار إعادة تنشيط حساب مجمّد بسبب الغياب.
 */
enum AssessmentType: string
{
    case Quiz = 'quiz';

    case Exam = 'exam';

    case Placement = 'placement';

    case Reactivation = 'reactivation';

    public function label(): string
    {
        return __('assessments::types.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Quiz => 'info',
            self::Exam => 'danger',
            self::Placement => 'warning',
            self::Reactivation => 'success',
        };
    }
}
