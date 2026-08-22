<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * عُدّلت رسالة بعد إرسالها ضمن نافذة التعديل المسموحة.
 */
final class MessageEdited extends MessagingEvent
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $organizationId,
        public readonly string $editorUserId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.message_edited';
    }

    public function payload(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'organization_id' => $this->organizationId,
            'editor_user_id' => $this->editorUserId,
        ];
    }
}
