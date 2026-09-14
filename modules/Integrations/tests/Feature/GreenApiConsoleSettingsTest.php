<?php

declare(strict_types=1);

namespace Modules\Integrations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Modules\Identity\Domain\Models\User;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class GreenApiConsoleSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'organizations.view', 'settings.manage',
        'integrations.connection.update',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'console.enabled' => true,
            'notifications.channels.whatsapp.enabled' => false,
            'app.url' => 'https://school.example',
        ]);
        $this->withoutVite();
        foreach ($this->permissions as $permission) {
            Gate::define($permission, fn (): bool => in_array($permission, $this->permissions, true));
        }
    }

    public function test_admin_can_verify_and_save_an_encrypted_connection_without_exposing_token(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        Http::fake(['7107.api.greenapi.com/*' => Http::response(['stateInstance' => 'authorized'])]);
        $this->post('/manage/settings/green-api', $this->payload())->assertSessionHasNoErrors();

        $connection = IntegrationConnection::query()->sole();
        self::assertSame($organization->id, $connection->organization_id);
        self::assertSame('secretToken123', $connection->credentials['token']);
        self::assertStringNotContainsString('secretToken123', (string) DB::table('integration_connections')->value('credentials'));
        self::assertTrue(app(GreenApiConnections::class)->isEnabled($organization->id));
        self::assertFalse(app(GreenApiConnections::class)->isEnabled(Organization::factory()->create()->id));
        self::assertStringNotContainsString('secretToken123', json_encode($this->props(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('secretToken123', (string) DB::table('audit_log')->where('action', 'console.settings.green_api')->value('new_values'));
        Http::assertSentCount(1);
    }

    public function test_failed_verification_and_unauthorized_actor_cannot_activate_connection(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        Http::fake(['7107.api.greenapi.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);
        $this->post('/manage/settings/green-api', $this->payload())->assertSessionHasErrors('token');
        self::assertSame(0, IntegrationConnection::query()->count());
        self::assertSame(0, DB::table('audit_log')->where('action', 'console.settings.green_api')->count());

        $payload = $this->payload();
        $this->permissions = ['admin.panel.access', 'organizations.view'];
        $this->post('/manage/settings/green-api', $payload)->assertForbidden();
        self::assertFalse(app(GreenApiConnections::class)->isEnabled($organization->id));
    }

    public function test_admin_can_register_a_secured_webhook_and_replayed_messages_are_idempotent(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        Http::fake([
            '7107.api.greenapi.com/*getStateInstance*' => Http::response(['stateInstance' => 'authorized']),
            '7107.api.greenapi.com/*setSettings*' => Http::response(['saveSettings' => true]),
        ]);
        $this->post('/manage/settings/green-api', $this->payload())->assertSessionHasNoErrors();
        $this->post('/manage/settings/green-api/webhook', ['reason' => 'تفعيل الردود الواردة'])
            ->assertSessionHasNoErrors();
        $connection = IntegrationConnection::query()->sole();
        self::assertTrue($connection->settings['webhook_registered']);
        $webhookToken = $connection->credentials['webhook_token'];
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/setSettings/')
            && $request['webhookUrl'] === 'https://school.example/api/webhooks/green-api'
            && $request['webhookUrlToken'] === 'Bearer '.$webhookToken
            && $request['outgoingAPIMessageWebhook'] === 'yes',
        );

        $payload = [
            'typeWebhook' => 'incomingMessageReceived',
            'instanceData' => ['idInstance' => 710722736548],
            'timestamp' => 1789410000,
            'idMessage' => 'MESSAGE-ONE',
            'senderData' => ['chatId' => '201001234567@c.us'],
            'messageData' => [
                'typeMessage' => 'textMessage',
                'textMessageData' => ['textMessage' => 'أهلاً'],
            ],
        ];
        $this->postJson('/api/webhooks/green-api', $payload)->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$webhookToken)
            ->postJson('/api/webhooks/green-api', $payload)->assertOk();
        $this->postJson('/api/webhooks/green-api', $payload)->assertOk();
        self::assertSame(1, WhatsappInbound::query()->count());
        self::assertSame($organization->id, WhatsappInbound::query()->sole()->organization_id);
        self::assertSame('أهلاً', WhatsappInbound::query()->sole()->body);
        $this->postJson('/api/webhooks/green-api', [
            'typeWebhook' => 'stateInstanceChanged',
            'instanceData' => ['idInstance' => 710722736548],
            'stateInstance' => 'notAuthorized',
        ])->assertOk();
        self::assertSame('notAuthorized', IntegrationConnection::query()->sole()->settings['state']);
    }

    public function test_saved_connection_sends_from_database_and_tracks_provider_delivery_status(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        Http::fake([
            '7107.api.greenapi.com/*getStateInstance*' => Http::response(['stateInstance' => 'authorized']),
            '7107.api.greenapi.com/*sendMessage*' => Http::response(['idMessage' => 'OUT-123']),
        ]);
        $this->post('/manage/settings/green-api', $this->payload())->assertSessionHasNoErrors();
        $connection = IntegrationConnection::query()->sole();

        config(['notifications.channels.whatsapp.enabled' => false]);
        $outbox = NotificationOutbox::factory()->withChannel(Channel::Whatsapp)->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'payload' => ['phone' => '01001234567', 'phone_country' => 'EG'],
            'scheduled_for' => now('UTC'),
        ]);
        app()->call([new SendQueuedNotification($outbox->id), 'handle']);

        self::assertSame(OutboxStatus::Sent, $outbox->refresh()->status);
        self::assertSame('OUT-123', $outbox->external_message_id);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage/')
            && $request->url() === 'https://7107.api.greenapi.com/waInstance710722736548/sendMessage/secretToken123'
            && $request['chatId'] === '201001234567@c.us');

        $status = [
            'typeWebhook' => 'outgoingMessageStatus',
            'instanceData' => ['idInstance' => 710722736548],
            'idMessage' => 'OUT-123',
            'chatId' => '201001234567@c.us',
            'timestamp' => 1789410000,
            'sendByApi' => true,
            'status' => 'delivered',
        ];
        $this->withHeader('Authorization', 'Bearer '.$connection->credentials['webhook_token'])
            ->postJson('/api/webhooks/green-api', $status)->assertOk();
        self::assertSame('delivered', $outbox->refresh()->provider_status);
        $this->postJson('/api/webhooks/green-api', [...$status, 'status' => 'sent'])->assertOk();
        self::assertSame('delivered', $outbox->refresh()->provider_status);
        $this->postJson('/api/webhooks/green-api', [
            ...$status, 'status' => 'failed', 'description' => 'Recipient unavailable',
        ])->assertOk();
        self::assertSame('failed', $outbox->refresh()->provider_status);
        self::assertSame('Recipient unavailable', $outbox->failure_reason);
    }

    /** @return array{Organization, User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization($organization->id)->create();

        return [$organization, $actor];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'api_url' => 'https://7107.api.greenapi.com',
            'instance_id' => '710722736548',
            'token' => 'secretToken123',
            'enabled' => true,
            'reason' => 'إعداد اتصال واتساب',
            'version' => $this->props()['greenApi']['version'],
        ];
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        return $this->get('/manage/settings')->assertOk()->viewData('page')['props'];
    }
}
