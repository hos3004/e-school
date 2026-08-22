<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

use Modules\Integrations\Domain\Enums\WebhookDirection;

final class WebhookDeliveryRecorded extends WebhookDeliveryEvent
{
    public function __construct(
        string $deliveryId,
        string $connectionId,
        string $organizationId,
        string $eventType,
        public readonly WebhookDirection $direction,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($deliveryId, $connectionId, $organizationId, $eventType, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'integrations.webhook_delivery_recorded';
    }

    public function payload(): array
    {
        return [
            'delivery_id' => $this->deliveryId,
            'connection_id' => $this->connectionId,
            'organization_id' => $this->organizationId,
            'event_type' => $this->eventType,
            'direction' => $this->direction->value,
        ];
    }
}
