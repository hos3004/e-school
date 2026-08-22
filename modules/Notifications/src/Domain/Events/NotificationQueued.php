<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Events;

use Carbon\CarbonInterface;

/**
 * قُيّدت رسالة في صندوق الإرسال وستُسلَّم في موعدها.
 */
final class NotificationQueued extends NotificationEvent
{
    public readonly string $sourceEventId;

    public function __construct(
        string $outboxId,
        string $organizationId,
        string $userId,
        public readonly string $category,
        public readonly string $channel,
        public readonly string $locale,
        public readonly string $eventName,
        string $eventId,
        public readonly string $idempotencyKey,
        public readonly CarbonInterface $scheduledFor,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        $this->sourceEventId = $eventId;
        parent::__construct($outboxId, $organizationId, $userId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'notifications.queued';
    }

    public function payload(): array
    {
        return [
            'outbox_id' => $this->outboxId,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'category' => $this->category,
            'channel' => $this->channel,
            'locale' => $this->locale,
            'event_name' => $this->eventName,
            'event_id' => $this->sourceEventId,
            'idempotency_key' => $this->idempotencyKey,
            'scheduled_for' => $this->scheduledFor->toIso8601String(),
        ];
    }
}
