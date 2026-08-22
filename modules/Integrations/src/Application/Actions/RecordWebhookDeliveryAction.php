<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Enums\WebhookDirection;
use Modules\Integrations\Domain\Events\WebhookDeliveryRecorded;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل إيصال Webhook جديد في الطابور بحالة Pending.
 *
 * حراس: الاتصال موجود، والإرسال الصادر يتطلب اتصالًا مُفعَّلًا.
 */
final readonly class RecordWebhookDeliveryAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(
        string $connectionId,
        string $eventType,
        WebhookDirection $direction = WebhookDirection::Outbound,
        array $payload = [],
    ): IntegrationWebhookDelivery {
        $connection = IntegrationConnection::query()->find($connectionId);

        if ($connection === null) {
            throw BusinessRuleViolation::make(
                'integrations.connection_not_found',
                'integrations::errors.connection_not_found',
                ['connection_id' => $connectionId],
            );
        }

        if ($direction === WebhookDirection::Outbound && !$connection->status->acceptsDeliveries()) {
            throw BusinessRuleViolation::make(
                'integrations.connection_not_active',
                'integrations::errors.connection_not_active',
                ['status' => $connection->status->value],
            );
        }

        $delivery = $this->transaction->run(function () use ($connection, $eventType, $direction, $payload): IntegrationWebhookDelivery {
            return IntegrationWebhookDelivery::query()->create([
                'connection_id' => (string) $connection->getKey(),
                'direction' => $direction,
                'event_type' => $eventType,
                'status' => DeliveryStatus::Pending,
                'attempts' => 0,
                'payload' => $payload === [] ? null : $payload,
            ]);
        });

        $this->events->dispatch(new WebhookDeliveryRecorded(
            deliveryId: (string) $delivery->getKey(),
            connectionId: (string) $connection->getKey(),
            organizationId: (string) $connection->organization_id,
            eventType: $eventType,
            direction: $direction,
        ));

        return $delivery;
    }
}
