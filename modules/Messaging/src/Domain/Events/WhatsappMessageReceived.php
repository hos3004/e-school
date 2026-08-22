<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * وُصلت رسالة واردة من واتساب إلى المؤسسة.
 */
final class WhatsappMessageReceived extends MessagingEvent
{
    public function __construct(
        public readonly string $inboundId,
        public readonly string $organizationId,
        public readonly string $fromPhone,
        public readonly ?string $matchedUserId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.whatsapp_message_received';
    }

    public function payload(): array
    {
        return [
            'inbound_id' => $this->inboundId,
            'organization_id' => $this->organizationId,
            'from_phone' => $this->fromPhone,
            'matched_user_id' => $this->matchedUserId,
        ];
    }
}
