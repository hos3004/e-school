<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Enums;

/**
 * دورة حياة طلب إعادة التفعيل بعد التجميد.
 *
 * لا انتقال خارج هذه المسارات — كل تغيير حالة يمر بـ canTransitionTo.
 */
enum ReactivationStatus: string
{
    /** مُقدَّم وبانتظار المراجعة. */
    case Pending = 'pending';

    /** قيد التقييم (اختبار الجدية جارٍ). */
    case UnderReview = 'under_review';

    /** مقبول — عاد الطالب إلى Active. */
    case Approved = 'approved';

    /** مرفوض — يمكن للطالب التقديم مجددًا ضمن حدود المحاولات. */
    case Rejected = 'rejected';

    /** ملغى من مقدِّم الطلب نفسه قبل القرار. */
    case Cancelled = 'cancelled';

    /**
     * الانتقالات المسموحة — أي مسار آخر مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::UnderReview, self::Approved, self::Rejected, self::Cancelled],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return __('discipline::reactivation.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::UnderReview => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
