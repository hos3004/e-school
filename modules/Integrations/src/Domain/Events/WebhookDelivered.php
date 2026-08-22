<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

use Carbon\CarbonImmutable;

final class WebhookDelivered extends WebhookDeliveryEvent
{
    public function __construct(
        string $deliveryId,
        string $connectionId,
        string $organizationId,
        string $eventType,
        public readonly int $attempts,
        public readonly int $responseCode,
        public readonly CarbonImmutable $deliveredAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($deliveryId, $connectionId, $organizationId, $eventType, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'integrations.webhook_delivered';
    }

    public function payload(): array
    {
        return [
            'delivery_id' => $this->deliveryId,
            'connection_id' => $this->connectionId,
            'organization_id' => $this->organizationId,
            'event_type' => $this->eventType,
            'attempts' => $this->attempts,
            'response_code' => $this->responseCode,
            'delivered_at' => $this->deliveredAt->toIso8601String(),
        ];
    }
}
