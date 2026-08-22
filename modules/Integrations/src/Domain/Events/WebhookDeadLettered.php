<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

use Carbon\CarbonImmutable;

/**
 * نفدت كل محاولات إيصال الـ Webhook — سُجّل في صندوق الرسائل الميتة
 * ويلزم تدخل بشري لإعادة إرساله.
 */
final class WebhookDeadLettered extends WebhookDeliveryEvent
{
    public function __construct(
        string $deliveryId,
        string $connectionId,
        string $organizationId,
        string $eventType,
        public readonly int $attempts,
        public readonly ?int $responseCode,
        public readonly CarbonImmutable $failedAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($deliveryId, $connectionId, $organizationId, $eventType, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'integrations.webhook_dead_lettered';
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
            'failed_at' => $this->failedAt->toIso8601String(),
        ];
    }
}
