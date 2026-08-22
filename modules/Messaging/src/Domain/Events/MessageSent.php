<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * أُرسلت رسالة داخل محادثة.
 */
final class MessageSent extends MessagingEvent
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $organizationId,
        public readonly string $senderUserId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.message_sent';
    }

    public function payload(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'organization_id' => $this->organizationId,
            'sender_user_id' => $this->senderUserId,
        ];
    }
}
