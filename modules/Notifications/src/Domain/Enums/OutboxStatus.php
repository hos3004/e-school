<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * دورة حياة رسالة في صندوق الإرسال (outbox).
 *
 * queued   → في الانتظار حتى موعد scheduled_for.
 * sending  → المرسِل التقطها الآن ويحاول التسليم.
 * sent     → سُلِّمت فعليًا — حالة نهائية.
 * failed   → استُنفد الحد الأقصى للمحاولات — نهائية إلا بإعادة يدوية.
 * cancelled→ أُلغيت قبل الإرسال — نهائية.
 * suppressed→ مُنع عمدًا كمكرر خلال نافذة idempotency — نهائية، لا يُرسل.
 */
enum OutboxStatus: string
{
    case Queued = 'queued';

    case Sending = 'sending';

    case Sent = 'sent';

    case Failed = 'failed';

    case Cancelled = 'cancelled';

    case Suppressed = 'suppressed';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Queued => [
                self::Sending,
                self::Cancelled,
            ],
            self::Sending => [
                self::Sent,
                self::Failed,
                self::Queued,
            ],
            self::Failed => [
                self::Queued,
            ],

            // حالات نهائية — لا خروج منها.
            self::Sent,
            self::Cancelled,
            self::Suppressed => [],
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
        return $this === self::Queued;
    }

    public function label(): string
    {
        return __('notifications::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'blue',
            self::Sending => 'amber',
            self::Sent => 'emerald',
            self::Failed => 'red',
            self::Cancelled => 'gray',
            self::Suppressed => 'zinc',
        };
    }
}
