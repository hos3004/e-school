<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Enums;

/**
 * حالة مستلم واحد داخل حملة.
 *
 * invalid   → رقمه لم يُقبل عند بناء القائمة، فلا تُوزَّع له مهمة أصلًا.
 * pending   → ينتظر موعده.
 * sent      → قبله المزوّد.
 * failed    → رفضه المزوّد أو تعذّر الاتصال.
 * cancelled → أُوقفت الحملة أو أُغلقت القناة قبل أن يأتي دوره.
 */
enum WhatsappCampaignRecipientStatus: string
{
    case Invalid = 'invalid';

    case Pending = 'pending';

    case Sent = 'sent';

    case Failed = 'failed';

    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Sent, self::Failed, self::Cancelled],
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
}
