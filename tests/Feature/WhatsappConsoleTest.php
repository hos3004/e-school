<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Infrastructure\Gateways\GreenApiGateway;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Testing\Fixtures;

function whatsappConnection(ConnectionStatus $status = ConnectionStatus::Active): IntegrationConnection
{
    $provider = IntegrationProvider::query()->firstOrCreate(
        ['key' => 'green_api'],
        [
            'name' => ['ar' => 'Green API', 'en' => 'Green API'],
            'category' => 'messaging',
            'driver' => 'green_api',
            'is_active' => true,
        ],
    );

    $connection = new IntegrationConnection([
        'organization_id' => Fixtures::organizationId(),
        'provider_id' => $provider->id,
        'status' => $status,
    ]);
    $connection->settings = [
        'api_url' => 'https://7107.api.greenapi.com',
        'instance_id' => '1234567890',
        'state' => 'authorized',
    ];
    $connection->credentials = ['token' => 'abc123token', 'webhook_token' => Str::random(48)];
    $connection->activated_at = now('UTC');
    $connection->save();

    return $connection;
}

function whatsappOutboxRow(string $organizationId): NotificationOutbox
{
    $row = new NotificationOutbox([
        'organization_id' => $organizationId,
        'user_id' => Fixtures::userId(),
        'category' => 'system_alert',
        'channel' => 'whatsapp',
        'locale' => 'ar',
        'event_name' => 'test.event',
        'event_id' => (string) Str::ulid(),
        'body' => ['ar' => 'نص'],
        'payload' => [],
        'idempotency_key' => (string) Str::ulid(),
        'status' => OutboxStatus::Queued,
        'scheduled_for' => now('UTC'),
        'attempts' => 0,
    ]);
    $row->save();

    return $row;
}

it('turns the channel off and back on without re-entering the token', function (): void {
    $organizationId = Fixtures::organizationId();
    whatsappConnection();

    /** @var GreenApiConnections $connections */
    $connections = app(GreenApiConnections::class);

    expect($connections->isChannelEnabled($organizationId))->toBeTrue();

    $changed = $connections->setChannelActive($organizationId, false, Fixtures::userId(), 'حادثة إرسال');

    expect($changed)->toBeTrue()
        ->and($connections->isChannelEnabled($organizationId))->toBeFalse()
        // التوكن يبقى محفوظًا، فالتشغيل لا يحتاج نسخه من لوحة المزوّد.
        ->and((string) (IntegrationConnection::query()->firstOrFail()->credentials['token'] ?? ''))->toBe('abc123token');

    $connections->setChannelActive($organizationId, true, Fixtures::userId(), 'انتهت الحادثة');

    expect($connections->isChannelEnabled($organizationId))->toBeTrue();
});

it('reports no change when the channel is already in the requested state', function (): void {
    $organizationId = Fixtures::organizationId();
    whatsappConnection();

    expect(app(GreenApiConnections::class)->setChannelActive(
        $organizationId,
        true,
        Fixtures::userId(),
        'لا تغيير',
    ))->toBeFalse();
});

it('refuses to send once the channel is off, even for a message already queued', function (): void {
    $organizationId = Fixtures::organizationId();
    whatsappConnection();

    /** @var GreenApiConnections $connections */
    $connections = app(GreenApiConnections::class);
    $connections->setChannelActive($organizationId, false, Fixtures::userId(), 'إيقاف');

    $result = app(GreenApiGateway::class)->send(new GatewayMessage(
        messageId: (string) Str::ulid(),
        organizationId: $organizationId,
        recipientId: Fixtures::userId(),
        category: 'system_alert',
        channel: 'whatsapp',
        locale: 'ar',
        eventName: 'test.event',
        eventId: (string) Str::ulid(),
        correlationId: null,
        subject: null,
        body: ['ar' => 'نص'],
        payload: ['phone' => '+201000000000', 'phone_country' => 'EG'],
    ));

    expect($result->isAccepted())->toBeFalse()
        ->and($result->error())->toBe('whatsapp_configuration_invalid');
});

it('cancels the queued whatsapp messages when the switch is turned off', function (): void {
    $organizationId = Fixtures::organizationId();
    whatsappConnection();
    $row = whatsappOutboxRow($organizationId);

    $admin = User::query()->findOrFail(Fixtures::userId());
    $admin->forceFill(['organization_id' => $organizationId])->save();

    // الصلاحية تُعرَّف في البوابة حتى لا يعتمد الفحص على بذور الأدوار،
    // ومسارات الكونسول تعيد 404 ما لم تكن اللوحة مفعّلة في الإعداد.
    config()->set('console.enabled', true);
    Gate::define('admin.panel.access', static fn (): bool => true);
    Gate::define('integrations.connection.update', static fn (): bool => true);

    $this->actingAs($admin)->post('/manage/whatsapp/toggle', [
        'active' => false,
        'reason' => 'إيقاف طارئ أثناء الفحص',
    ])->assertRedirect();

    expect(NotificationOutbox::query()->findOrFail($row->getKey())->status)
        ->toBe(OutboxStatus::Cancelled);
});

it('stamps an automatic notice on system messages and leaves manual ones clean', function (): void {
    $organizationId = Fixtures::organizationId();
    whatsappConnection();

    $gateway = app(GreenApiGateway::class);
    $reflection = new ReflectionMethod($gateway, 'text');

    $automatic = $reflection->invoke($gateway, new GatewayMessage(
        messageId: (string) Str::ulid(),
        organizationId: $organizationId,
        recipientId: Fixtures::userId(),
        category: 'session_changed',
        channel: 'whatsapp',
        locale: 'ar',
        eventName: 'session.scheduled',
        eventId: (string) Str::ulid(),
        correlationId: null,
        subject: null,
        body: ['ar' => 'تم جدولة حصة'],
        payload: [],
    ));

    $manual = $reflection->invoke($gateway, new GatewayMessage(
        messageId: (string) Str::ulid(),
        organizationId: $organizationId,
        recipientId: Fixtures::userId(),
        category: 'system_alert',
        channel: 'whatsapp',
        locale: 'ar',
        eventName: 'notifications.manual',
        eventId: (string) Str::ulid(),
        correlationId: null,
        subject: null,
        body: ['ar' => 'رسالة من الإدارة'],
        payload: ['manual' => true],
    ));

    $notice = (string) __('integrations::whatsapp.automatic_notice');

    expect($automatic)->toContain($notice)
        ->and($automatic)->toStartWith('تم جدولة حصة')
        ->and($manual)->toBe('رسالة من الإدارة')
        ->and($manual)->not->toContain($notice);
});

it('previews the real recipients of a schedule without queueing anything', function (): void {
    $organizationId = Fixtures::organizationId();
    whatsappConnection();

    $admin = User::query()->findOrFail(Fixtures::userId());
    $admin->forceFill(['organization_id' => $organizationId])->save();
    config()->set('console.enabled', true);
    Gate::define('admin.panel.access', static fn (): bool => true);
    Gate::define('notifications.outbox.create', static fn (): bool => true);

    $before = NotificationOutbox::query()->count();

    $this->actingAs($admin)
        ->postJson('/manage/whatsapp/preview', [
            'recipient_type' => 'course',
            'target_id' => 'not-a-real-course',
            'audience' => 'all',
            'subject' => 'عنوان',
            'body' => 'نص',
        ])
        ->assertStatus(422);

    // المحاكاة لا تكتب شيئًا مهما كانت نتيجتها.
    expect(NotificationOutbox::query()->count())->toBe($before);
});
