<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Integrations\Application\Actions\RecordWebhookDeliveryAction;
use Modules\Integrations\Application\Actions\RequeueDeadDeliveryAction;
use Modules\Integrations\Application\Actions\SettleWebhookDeliveryAction;
use Modules\Integrations\Database\Factories\IntegrationConnectionFactory;
use Modules\Integrations\Database\Factories\IntegrationWebhookDeliveryFactory;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Enums\WebhookDirection;
use Modules\Integrations\Domain\Events\WebhookDeadLettered;
use Modules\Integrations\Domain\Events\WebhookDelivered;
use Modules\Integrations\Domain\Events\WebhookDeliveryRecorded;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Shared\Support\BusinessRuleViolation;

function deliveryActiveConnection(): IntegrationConnection
{
    return IntegrationConnectionFactory::new()->create([
        'status' => ConnectionStatus::Active,
    ]);
}

it('records an outbound delivery only on an active connection', function (): void {
    Event::fake([WebhookDeliveryRecorded::class]);

    $connection = deliveryActiveConnection();

    $delivery = app(RecordWebhookDeliveryAction::class)->execute(
        connectionId: (string) $connection->getKey(),
        eventType: 'attendance.recorded',
        payload: ['attendance_id' => 'a1'],
    );

    expect($delivery->exists)->toBeTrue()
        ->and($delivery->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery->attempts)->toBe(0);

    Event::assertDispatched(WebhookDeliveryRecorded::class);
});

it('refuses an outbound delivery on a disabled connection', function (): void {
    $connection = IntegrationConnectionFactory::new()->create([
        'status' => ConnectionStatus::Disabled,
    ]);

    app(RecordWebhookDeliveryAction::class)->execute(
        connectionId: (string) $connection->getKey(),
        eventType: 'attendance.recorded',
    );
})->throws(BusinessRuleViolation::class);

it('marks a delivery delivered and publishes WebhookDelivered', function (): void {
    Event::fake([WebhookDelivered::class]);

    $connection = deliveryActiveConnection();
    $delivery = IntegrationWebhookDeliveryFactory::new()->create([
        'connection_id' => (string) $connection->getKey(),
    ]);

    $settled = app(SettleWebhookDeliveryAction::class)->execute($delivery, true, 200);

    expect($settled->status)->toBe(DeliveryStatus::Delivered)
        ->and((int) $settled->attempts)->toBe(1)
        ->and((int) $settled->response_code)->toBe(200);

    Event::assertDispatched(WebhookDelivered::class);
});

it('dead-letters a delivery after exhausting the configured attempts', function (): void {
    config()->set('integrations.webhooks.max_attempts', 3);
    config()->set('integrations.webhooks.retry_backoff_minutes', 15);

    Event::fake([WebhookDeadLettered::class]);

    $connection = deliveryActiveConnection();
    $action = app(SettleWebhookDeliveryAction::class);
    $delivery = IntegrationWebhookDeliveryFactory::new()->create([
        'connection_id' => (string) $connection->getKey(),
    ]);

    $delivery = $action->execute($delivery, false, 500);
    $delivery = $action->execute($delivery, false, 500);

    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and((int) $delivery->attempts)->toBe(2)
        ->and($delivery->next_retry_at)->toBeNull();

    $delivery = $action->execute($delivery, false, 503);

    expect($delivery->status)->toBe(DeliveryStatus::Dead)
        ->and((int) $delivery->attempts)->toBe(3)
        ->and($delivery->next_retry_at)->toBeNull();

    Event::assertDispatchedTimes(WebhookDeadLettered::class, 1);
});

it('requeues a dead delivery through the manual replay path', function (): void {
    $connection = deliveryActiveConnection();
    $delivery = IntegrationWebhookDeliveryFactory::new()->create([
        'connection_id' => (string) $connection->getKey(),
        'status' => DeliveryStatus::Dead,
        'attempts' => (int) config('integrations.webhooks.max_attempts'),
        'failed_at' => now(),
    ]);

    $requeued = app(RequeueDeadDeliveryAction::class)->execute($delivery);

    expect($requeued->status)->toBe(DeliveryStatus::Retrying)
        ->and($requeued->next_retry_at)->not->toBeNull();
});

it('refuses to requeue a delivery that never died', function (): void {
    $connection = deliveryActiveConnection();
    $delivery = IntegrationWebhookDeliveryFactory::new()->create([
        'connection_id' => (string) $connection->getKey(),
        'status' => DeliveryStatus::Pending,
    ]);

    app(RequeueDeadDeliveryAction::class)->execute($delivery);
})->throws(BusinessRuleViolation::class);

it('accepts inbound deliveries regardless of connection state', function (): void {
    $connection = IntegrationConnectionFactory::new()->create([
        'status' => ConnectionStatus::Pending,
    ]);

    $delivery = app(RecordWebhookDeliveryAction::class)->execute(
        connectionId: (string) $connection->getKey(),
        eventType: 'payment.captured',
        direction: WebhookDirection::Inbound,
        payload: ['amount_cents' => 15000],
    );

    expect($delivery->direction)->toBe(WebhookDirection::Inbound);
});
