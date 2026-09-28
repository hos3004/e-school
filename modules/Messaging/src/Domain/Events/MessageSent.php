<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * أُرسلت رسالة داخل محادثة.
 */
final class MessageSent extends MessagingEvent
{
    /**
     * @param list<string> $recipientUserIds مشاركو المحادثة عدا المُرسل — تُحسب هنا
     *                                       لأن Notifications لا يعرف جدول المشاركين ولا يجوز أن يستوعبه.
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $organizationId,
        public readonly string $senderUserId,
        public readonly array $recipientUserIds,
        public readonly string $senderName,
        public readonly string $messagePreview,
        public readonly ?string $conversationSubject,
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
            'recipient_user_ids' => $this->recipientUserIds,
            'sender_name' => $this->senderName,
            'message_preview' => $this->messagePreview,
            'conversation_subject' => $this->conversationSubject,
        ];
    }
}
