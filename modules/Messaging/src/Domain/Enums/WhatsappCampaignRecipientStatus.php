<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Enums;

/**
 * حالة مستلم واحد داخل حملة.
 *
 * invalid   → رقمه لم يُقبل عند بناء القائمة، فلا تُوزَّع له مهمة أصلًا.
 * pending   → ينتظر موعده.
 * sending   → مهمةٌ حجزته الآن وتنادي المزوّد من أجله.
 * sent      → قبله المزوّد.
 * failed    → رفضه المزوّد أو تعذّر الاتصال أو انقطعت محاولته.
 * cancelled → أُوقفت الحملة أو أُغلقت القناة قبل أن يأتي دوره.
 *
 * حالة sending ليست ترفًا: بدونها يبقى السطر pending طوال نداء المزوّد — وهو
 * نداء قد يرفع خمسة ملفات — فتلتقطه مهمة ثانية وتُرسل للشخص نفسه مرة أخرى.
 */
enum WhatsappCampaignRecipientStatus: string
{
    case Invalid = 'invalid';

    case Pending = 'pending';

    case Sending = 'sending';

    case Sent = 'sent';

    case Failed = 'failed';

    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Sending, self::Cancelled],
            /*
             * sending → failed هو ما تفعله شبكة الأمان بمحاولة انقطعت. لا عودة
             * منها إلى pending: المزوّد ربما قبل الرسالة قبل الانقطاع، وإعادتها
             * إلى الانتظار تعني رسالة ثانية لمن وصلته الأولى.
             */
            self::Sending => [self::Sent, self::Failed],
            self::Invalid, self::Sent, self::Failed, self::Cancelled => [],
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

    /** هل ما زال هذا السطر يشغل الحملة فيمنع إنهاءها؟ */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Sending;
    }
}
