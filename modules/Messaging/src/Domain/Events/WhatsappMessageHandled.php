<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * تعامل موظف مع رسالة واتساب واردة وأغلقها.
 */
final class WhatsappMessageHandled extends MessagingEvent
{
    public function __construct(
        public readonly string $inboundId,
        public readonly string $organizationId,
        public readonly string $handledByUserId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.whatsapp_message_handled';
    }

    public function payload(): array
    {
        return [
            'inbound_id' => $this->inboundId,
            'organization_id' => $this->organizationId,
            'handled_by_user_id' => $this->handledByUserId,
        ];
    }
}
