<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Enums;

/**
 * دورة حياة إيصال Webhook.
 *
 * Pending → Delivered (نجاح) أو Retrying → Failed → Dead عند نفاد المحاولات.
 * الحد الأقصى للمحاولات يأتي من config('integrations.webhooks.max_attempts').
 */
enum DeliveryStatus: string
{
    /** في الطابور ولم تُجرَ أول محاولة بعد. */
    case Pending = 'pending';

    /** فشلت محاولة وأُعيدت للجدولة تلقائيًا. */
    case Retrying = 'retrying';

    /** وصلت واستقبل المزوّد برمز نجاح. */
    case Delivered = 'delivered';

    /** فشلت محاولة — تنتظر قرارًا: إعادة يدوية أو إعلان موت. */
    case Failed = 'failed';

    /** نفدت كل المحاولات — سُجلت في صندوق الرسائل الميتة. */
    case Dead = 'dead';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Delivered,
                self::Retrying,
                self::Failed,
                self::Dead,
            ],
            self::Retrying => [
                self::Delivered,
                self::Failed,
                self::Dead,
            ],
            self::Failed => [
                self::Retrying,
                self::Dead,
            ],
            self::Dead => [
                self::Retrying,
            ],

            // نهائية — الإيصال وصل بنجاح ولا يُلمس بعدها.
            self::Delivered => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل هذه حالة نهائية لا تقبل محاولات جديدة؟ */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Dead], true);
    }

    public function label(): string
    {
        return __('integrations::status.delivery.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Retrying => 'amber',
            self::Delivered => 'emerald',
            self::Failed => 'orange',
            self::Dead => 'rose',
        };
    }
}
