<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Enums;

/**
 * دورة طلب التأجيل — الطريق الوحيد المتاح للطالب حاليًا لتغيير موعد حصة.
 *
 * تسلسل العميل بالحرف:
 *   الطالب يطلب التأجيل ويقترح موعدًا (قبل الحصة بربع ساعة على الأقل)
 *   ← إشعار للمعلم وللإدارة
 *   ← المعلم يؤكد الموعد المقترح أو يرشّح موعدًا آخر
 *   ← المعلم يحدد الموعد النهائي
 *   ← تُنشأ حصة تلافي مرتبطة بالحصة الأصلية.
 *
 * الإلغاء لا يقابله حصة تلافي إطلاقًا.
 */
enum PostponementStatus: string
{
    /** قُدّم الطلب وينتظر رد المعلم. */
    case Requested = 'requested';

    /** المعلم اقترح موعدًا بديلًا وينتظر موافقة الطالب. */
    case AlternativeProposed = 'alternative_proposed';

    /** اتُّفق على الموعد وأُنشئت حصة التلافي. */
    case Scheduled = 'scheduled';

    /** أُقيمت حصة التلافي — تُحرَّر مستحقات المعلم المؤجَّلة. */
    case Fulfilled = 'fulfilled';

    /** رفضه المعلم أو الإدارة. */
    case Rejected = 'rejected';

    /** سحبه الطالب قبل البت فيه. */
    case Withdrawn = 'withdrawn';

    /** انقضت مهلة رد المعلم ولم يُبَت فيه — يُصعَّد للإدارة. */
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [
                self::Scheduled,
                self::AlternativeProposed,
                self::Rejected,
                self::Withdrawn,
                self::Expired,
            ],
            self::AlternativeProposed => [
                self::Scheduled,
                self::Rejected,
                self::Withdrawn,
                self::Expired,
            ],
            self::Scheduled => [self::Fulfilled, self::Rejected],
            self::Expired => [self::Scheduled, self::Rejected],

            self::Fulfilled, self::Rejected, self::Withdrawn => [],
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

    /** هل ما زال الطلب ينتظر إجراءً من أحد؟ */
    public function isPending(): bool
    {
        return in_array($this, [self::Requested, self::AlternativeProposed, self::Expired], true);
    }

    /** من عليه الدور الآن؟ */
    public function awaitingActionFrom(): ?string
    {
        return match ($this) {
            self::Requested => 'teacher',
            self::AlternativeProposed => 'student',
            self::Expired => 'admin',
            default => null,
        };
    }

    public function label(): string
    {
        return __('scheduling::postponement.'.$this->value);
    }
}
