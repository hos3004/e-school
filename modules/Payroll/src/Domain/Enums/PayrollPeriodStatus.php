<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Enums;

/**
 * دورة حياة فترة المستحقات.
 *
 * بعد Paid تُقفل الفترة نهائيًا. أي تصحيح لاحق لا يعدّل التاريخ،
 * بل يُنشئ قيدة تسوية في الفترة التالية. هذه ليست تفضيلًا بل شرط سلامة مالية.
 */
enum PayrollPeriodStatus: string
{
    /** الفترة مفتوحة وتستقبل قيودًا من الحصص المُقفلة. */
    case Open = 'open';

    /** الاحتساب جارٍ — الفترة مغلقة أمام القيود الجديدة. */
    case Calculating = 'calculating';

    /** تحت مراجعة المشرف. */
    case UnderReview = 'under_review';

    /** اعتُمدت ماليًا وتنتظر الصرف. */
    case Approved = 'approved';

    /** صُرفت. */
    case Paid = 'paid';

    /** مقفلة — لا تعديل بأي حال. */
    case Locked = 'locked';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Calculating],
            self::Calculating => [self::UnderReview, self::Open],
            self::UnderReview => [self::Approved, self::Calculating],
            self::Approved => [self::Paid, self::UnderReview],
            self::Paid => [self::Locked],
            self::Locked => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل تقبل الفترة قيودًا جديدة من الحصص؟ */
    public function acceptsEntries(): bool
    {
        return $this === self::Open;
    }

    /** هل تقبل تسويات (مكافأة/خصم) بعد الاحتساب؟ */
    public function acceptsAdjustments(): bool
    {
        return in_array($this, [self::Open, self::Calculating, self::UnderReview], true);
    }

    /** هل الأرقام مجمّدة لا تتغير؟ */
    public function isFrozen(): bool
    {
        return in_array($this, [self::Paid, self::Locked], true);
    }

    /** الصلاحية المطلوبة للانتقال إلى هذه الحالة. */
    public function requiredPermission(): ?string
    {
        return match ($this) {
            self::Calculating => 'payroll.calculate',
            self::UnderReview => 'payroll.review',
            self::Approved => 'payroll.approve',
            self::Paid => 'payroll.pay',
            self::Locked => 'payroll.lock',
            self::Open => null,
        };
    }

    public function label(): string
    {
        return __('payroll::period_status.'.$this->value);
    }
}
