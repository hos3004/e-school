<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Gateways\WhatsAppCloudGateway;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Testing\Fixtures;

beforeEach(function (): void {
    /** @var \Tests\TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false],
            'email' => ['enabled' => false],
            'whatsapp' => [
                'enabled' => true,
                'gateway' => WhatsAppCloudGateway::class,
                'token' => 'test-token',
                'phone_number_id' => '123456789',
                'api_version' => 'v23.0',
                'timeout_seconds' => 5,
                'retry_delays_milliseconds' => [],
            ],
        ],
        'notifications.categories.session_changed' => [
            'channels' => ['whatsapp'],
            'critical' => true,
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);
});

function whatsappPipelineRecipient(): string
{
    $userId = Fixtures::userId();

    DB::table('users')->where('id', $userId)->update([
        'phone' => '01001234567',
        'phone_country' => 'EG',
        'locale' => 'ar',
        'timezone' => 'UTC',
    ]);

    return $userId;
}

it('stores the Meta external id and status after a successful outbox delivery', function (): void {
    /** @var \Tests\TestCase $this */
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [[
                'id' => 'wamid.test-message',
                'message_status' => 'accepted',
            ]],
        ]),
    ]);

    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [whatsappPipelineRecipient()],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    $outbox = NotificationOutbox::query()->sole();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);
    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and($outbox->external_message_id)->toBe('wamid.test-message')
        ->and($outbox->provider_status)->toBe('accepted')
        ->and($outbox->failure_reason)->toBeNull()
        ->and($attempt->succeeded)->toBeTrue()
        ->and($attempt->external_message_id)->toBe('wamid.test-message')
        ->and($attempt->provider_response)->toMatchArray([
            'external_message_id' => 'wamid.test-message',
            'status' => 'accepted',
        ]);

    Http::assertSent(fn (Request $request): bool => $request->url()
        === 'https://graph.facebook.com/v23.0/123456789/messages'
        && $request['to'] === '+201001234567'
        && $request['type'] === 'template'
        && $request['template']['name'] === 'session_scheduled'
        && $request['template']['language']['code'] === 'ar');
});

it('marks a Meta 400 response as a permanent delivery failure with its reason', function (): void {
    /** @var \Tests\TestCase $this */
    Bus::fake([SendQueuedNotification::class]);
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => [
                'code' => 132001,
                'message' => 'Template does not exist',
            ],
        ], 400),
    ]);

    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [whatsappPipelineRecipient()],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    $outbox = NotificationOutbox::query()->sole();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);
    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($outbox->last_error_retryable)->toBeFalse()
        ->and($outbox->provider_status)->toBe('failed')
        ->and($outbox->failure_reason)->toBe('132001: Template does not exist')
        ->and($attempt->retryable)->toBeFalse()
        ->and($attempt->external_message_id)->toBeNull()
        ->and($attempt->provider_response)->toMatchArray([
            'status' => 'failed',
            'failure_reason' => '132001: Template does not exist',
            'provider_error_code' => 132001,
        ]);

    Bus::assertNotDispatched(SendQueuedNotification::class);
});
