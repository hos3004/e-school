<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Events;

/**
 * غيّر المستخدم تفضيله لفئة × قناة.
 */
final class NotificationPreferencesUpdated extends NotificationEvent
{
    public function __construct(
        string $outboxId,
        string $organizationId,
        string $userId,
        public readonly string $category,
        public readonly string $channel,
        public readonly bool $enabled,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($outboxId, $organizationId, $userId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'notifications.preferences_updated';
    }

    public function payload(): array
    {
        return [
            'preference_id' => $this->outboxId,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'category' => $this->category,
            'channel' => $this->channel,
            'enabled' => $this->enabled,
        ];
    }
}
