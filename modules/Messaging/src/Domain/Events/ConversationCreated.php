<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * أُنشئت محادثة جديدة.
 */
final class ConversationCreated extends MessagingEvent
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $organizationId,
        public readonly string $type,
        /** @var list<string> */
        public readonly array $participantUserIds,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.conversation_created';
    }

    public function payload(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'organization_id' => $this->organizationId,
            'type' => $this->type,
            'participant_user_ids' => $this->participantUserIds,
        ];
    }
}
