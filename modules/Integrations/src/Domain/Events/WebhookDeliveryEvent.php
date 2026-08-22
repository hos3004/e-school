<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أساس أحداث إيصالات Webhook — يثبّت المعرّفات المشتركة.
 */
abstract class WebhookDeliveryEvent extends DomainEvent
{
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $connectionId,
        public readonly string $organizationId,
        public readonly string $eventType,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'integrations';
    }
}
