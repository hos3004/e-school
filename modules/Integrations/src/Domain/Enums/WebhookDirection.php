<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Enums;

/**
 * اتجاه رسالة Webhook: صادرة من المنصة نحو المزوّد، أو واردة منه.
 */
enum WebhookDirection: string
{
    /** المنصة ترسل الحدث إلى المزوّد. */
    case Outbound = 'outbound';

    /** المزوّد يرسل إشعارًا إلى المنصة. */
    case Inbound = 'inbound';

    public function label(): string
    {
        return __('integrations::status.direction.'.$this->value);
    }
}
