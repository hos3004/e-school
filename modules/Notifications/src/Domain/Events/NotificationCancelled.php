<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Events;

/**
 * أُلغيت رسالة كانت في الانتظار قبل إرسالها.
 */
final class NotificationCancelled extends NotificationEvent
{
    public function __construct(
        string $outboxId,
        string $organizationId,
        string $userId,
        public readonly string $category,
        public readonly string $channel,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($outboxId, $organizationId, $userId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'notifications.cancelled';
    }

    public function payload(): array
    {
        return [
            'outbox_id' => $this->outboxId,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'category' => $this->category,
            'channel' => $this->channel,
            'reason' => $this->reason,
        ];
    }
}
