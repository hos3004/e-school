<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * وسم مشرف رسالة كمخالفة مع سبب موثّق.
 */
final class MessageFlagged extends MessagingEvent
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $organizationId,
        public readonly string $moderatorUserId,
        public readonly string $reason,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.message_flagged';
    }

    public function payload(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'organization_id' => $this->organizationId,
            'moderator_user_id' => $this->moderatorUserId,
            'reason' => $this->reason,
        ];
    }
}
