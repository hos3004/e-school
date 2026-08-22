<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * دورة حياة رسالة في صندوق الإرسال (outbox).
 *
 * pending  → في الانتظار حتى موعد scheduled_for.
 * sending  → المرسِل التقطها الآن ويحاول التسليم.
 * sent     → سُلِّمت فعليًا — حالة نهائية.
 * failed   → استُنفد الحد الأقصى للمحاولات — نهائية إلا بإعادة يدوية.
 * cancelled→ أُلغيت قبل الإرسال — نهائية.
 */
enum OutboxStatus: string
{
    case Pending = 'pending';

    case Sending = 'sending';

    case Sent = 'sent';

    case Failed = 'failed';

    case Cancelled = 'cancelled';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Sending,
                self::Cancelled,
            ],
            self::Sending => [
                self::Sent,
                self::Failed,
                self::Pending,
            ],
            self::Failed => [
                self::Pending,
            ],

            // حالات نهائية — لا خروج منها.
            self::Sent,
            self::Cancelled => [],
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

    /** هل ما زال بالإمكان محاولة تسليم الرسالة؟ */
    public function isDeliverable(): bool
    {
        return in_array($this, [self::Pending, self::Failed], true);
    }

    public function label(): string
    {
        return __('notifications::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'blue',
            self::Sending => 'amber',
            self::Sent => 'emerald',
            self::Failed => 'red',
            self::Cancelled => 'gray',
        };
    }
}
