<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

/**
 * أنواع الأسئلة المدعومة.
 *
 * النوع يحدد طريقة التصحيح:
 *  - mcq / true_false / short_answer : قابلة للتصحيح الآلي بمطابقة correct_answer.
 *  - essay                           : تحتاج تصحيحًا يدويًا من المعلم.
 */
enum QuestionType: string
{
    case Mcq = 'mcq';

    case TrueFalse = 'true_false';

    case ShortAnswer = 'short_answer';

    case Essay = 'essay';

    /** هل يمكن تصحيح هذا النوع آليًا بمقارنة الإجابة الصحيحة؟ */
    public function isAutoGradable(): bool
    {
        return match ($this) {
            self::Mcq, self::TrueFalse, self::ShortAnswer => true,
            self::Essay => false,
        };
    }

    public function label(): string
    {
        return __('assessments::question_types.'.$this->value);
    }
}
