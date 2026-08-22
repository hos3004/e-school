<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Enums;

/**
 * دورة حياة تسليم الطالب لنشاط.
 *
 * pending  : أُنشئ صف التسليم آليًا ولم يسلّم الطالب شيئًا بعد.
 * submitted: سلّم الطالب في الموعد.
 * late     : سلّم الطالب بعد الموعد (مع خصم التأخير إن انطبق).
 * graded   : رصد المعلم الدرجة والتغذية الراجعة — حالة نهائية.
 */
enum AssignmentSubmissionStatus: string
{
    case Pending = 'pending';

    case Submitted = 'submitted';

    case Late = 'late';

    case Graded = 'graded';

    /**
     * الانتقالات المسموحة — أي انتقال آخر مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Submitted,
                self::Late,
            ],
            self::Submitted => [
                self::Graded,
            ],
            self::Late => [
                self::Graded,
            ],

            // حالة نهائية — أي تصحيح لاحق بقيدة مراجعة موثّقة.
            self::Graded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل هذه الحالة نهائية؟ */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** هل سلّم الطالب فعليًا (في الوقت أو بعده)؟ */
    public function hasContent(): bool
    {
        return in_array($this, [self::Submitted, self::Late, self::Graded], true);
    }

    public function label(): string
    {
        return __('assignments::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Submitted => 'blue',
            self::Late => 'amber',
            self::Graded => 'emerald',
        };
    }
}
