<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationFailed;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;

final class NotificationsJobGatewayStub implements ChannelGateway
{
    public int $calls = 0;

    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        private readonly array $response = [],
        private readonly ?GatewayResult $result = null,
        private readonly ?Throwable $failure = null,
    ) {}

    public function send(GatewayMessage $message): GatewayResult
    {
        $this->calls++;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->result ?? GatewayResult::accepted($this->response);
    }
}

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('delivers in-app notifications and records the provider response', function (): void {
    $outbox = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->state(['scheduled_for' => now('UTC')])
        ->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and($outbox->attempts)->toBe(1)
        ->and($attempt->succeeded)->toBeTrue()
        ->and($attempt->provider_response)->toMatchArray([
            'driver' => 'in_app',
            'stored' => true,
            'outbox_id' => $outbox->id,
            'user_id' => $outbox->user_id,
        ]);
});

it('skips notifications that are no longer queued', function (): void {
    $gateway = new NotificationsJobGatewayStub;
    app()->instance(ChannelGateway::class, $gateway);

    $outbox = NotificationOutbox::factory()->sent()->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($gateway->calls)->toBe(0)
        ->and(NotificationDeliveryAttempt::query()->count())->toBe(0)
        ->and($outbox->refresh()->status)->toBe(OutboxStatus::Sent);
});

it('requeues retryable failures using the configured backoff', function (): void {
    Bus::fake([SendQueuedNotification::class]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC'));
    config([
        'notifications.delivery.backoff_seconds' => [90],
        'notifications.delivery.max_retries' => 5,
    ]);

    $gateway = new NotificationsJobGatewayStub(
        result: GatewayResult::rejected('temporary outage', true),
    );
    app()->instance(ChannelGateway::class, $gateway);

    $outbox = NotificationOutbox::factory()
        ->state(['scheduled_for' => CarbonImmutable::now('UTC')])
        ->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Queued)
        ->and($outbox->attempts)->toBe(1)
        ->and($outbox->last_error)->toBe('temporary outage')
        ->and($outbox->last_error_retryable)->toBeTrue()
        ->and($outbox->scheduled_for->equalTo(CarbonImmutable::now('UTC')->addSeconds(90)))->toBeTrue();

    Bus::assertDispatched(
        SendQueuedNotification::class,
        fn (SendQueuedNotification $job): bool => $job->outboxId === $outbox->id,
    );
});

it('fails permanently on the first non-retryable delivery error', function (): void {
    Bus::fake([SendQueuedNotification::class]);
    Event::fake([NotificationFailed::class]);
    config(['notifications.delivery.max_retries' => 5]);

    $gateway = new NotificationsJobGatewayStub(
        result: GatewayResult::rejected('invalid recipient', false),
    );
    app()->instance(ChannelGateway::class, $gateway);

    $outbox = NotificationOutbox::factory()->state([
        'scheduled_for' => now('UTC'),
    ])->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($outbox->attempts)->toBe(1)
        ->and($outbox->last_error_retryable)->toBeFalse()
        ->and(NotificationDeliveryAttempt::query()->sole()->retryable)->toBeFalse()
        ->and(NotificationDeliveryAttempt::query()->sole()->error)->toBe('invalid recipient');

    Bus::assertNotDispatched(SendQueuedNotification::class);
    Event::assertDispatched(NotificationFailed::class);
});

it('treats a missing configured gateway as a permanent failure', function (): void {
    Bus::fake([SendQueuedNotification::class]);
    config(['notifications.channels.email.gateway' => null]);

    $outbox = NotificationOutbox::factory()
        ->withChannel(Channel::Email)
        ->state(['scheduled_for' => now('UTC')])
        ->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($outbox->attempts)->toBe(1)
        ->and($outbox->last_error)->not->toBeEmpty();

    Bus::assertNotDispatched(SendQueuedNotification::class);
});

it('treats an unclassified throwable as permanent', function (): void {
    Bus::fake([SendQueuedNotification::class]);

    app()->instance(ChannelGateway::class, new NotificationsJobGatewayStub(
        failure: new RuntimeException('unexpected provider failure'),
    ));
    $outbox = NotificationOutbox::factory()->state([
        'scheduled_for' => now('UTC'),
    ])->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($outbox->last_error_retryable)->toBeFalse();

    Bus::assertNotDispatched(SendQueuedNotification::class);
});

it('does not deliver queued notifications before their scheduled time', function (): void {
    $gateway = new NotificationsJobGatewayStub;
    app()->instance(ChannelGateway::class, $gateway);

    $outbox = NotificationOutbox::factory()->state([
        'scheduled_for' => now('UTC')->addMinute(),
    ])->create();

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($gateway->calls)->toBe(0)
        ->and($outbox->refresh()->status)->toBe(OutboxStatus::Queued)
        ->and(NotificationDeliveryAttempt::query()->count())->toBe(0);
});
