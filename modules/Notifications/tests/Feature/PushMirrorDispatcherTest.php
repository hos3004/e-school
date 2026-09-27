<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Notifications\Domain\Contracts\FirebaseAccessTokenProvider;
use Modules\Notifications\Infrastructure\Gateways\InAppChannelGateway;
use Modules\Notifications\Infrastructure\Push\PushMirrorDispatcher;
use Modules\Notifications\Infrastructure\Push\ServiceAccountAccessTokenProvider;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    config()->set('notifications.channels.push.enabled', true);

    $this->app->bind(FirebaseAccessTokenProvider::class, fn () => new class implements FirebaseAccessTokenProvider
    {
        public function token(): string
        {
            return 'fake-access-token';
        }

        public function projectId(): string
        {
            return 'test-project';
        }
    });
});

function makeGatewayMessage(
    string $recipientId,
    array $subject = ['ar' => 'عنوان الإشعار'],
    array $body = ['ar' => 'نص الإشعار'],
    array $payload = [],
    string $eventName = 'session.postponement_request',
): GatewayMessage {
    return new GatewayMessage(
        messageId: (string) Str::ulid(),
        organizationId: Fixtures::organizationId(),
        recipientId: $recipientId,
        category: 'scheduling',
        channel: 'in_app',
        locale: 'ar',
        eventName: $eventName,
        eventId: (string) Str::ulid(),
        correlationId: null,
        subject: $subject,
        body: $body,
        payload: $payload,
    );
}

it('does nothing when push is disabled', function (): void {
    config()->set('notifications.channels.push.enabled', false);
    Http::fake();

    $userId = Fixtures::userId();
    UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    Http::assertNothingSent();
});

it('does nothing when the recipient has no active device', function (): void {
    Http::fake();

    $userId = Fixtures::userId();

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    Http::assertNothingSent();
});

it('sends the exact in-app title and body to every active device', function (): void {
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test-project/messages/1'], 200),
    ]);

    $userId = Fixtures::userId();
    UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);
    UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-2']);

    app(PushMirrorDispatcher::class)->dispatch(
        makeGatewayMessage(
            $userId,
            subject: ['ar' => 'طلب تأجيل'],
            body: ['ar' => 'وصل طلب تأجيل جديد'],
            payload: ['target_url' => '/teacher/postponements'],
        ),
    );

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/test-project/messages:send'
            && $body['message']['notification']['title'] === 'طلب تأجيل'
            && $body['message']['notification']['body'] === 'وصل طلب تأجيل جديد'
            && $body['message']['data']['target_url'] === '/teacher/postponements';
    });
});

it('revokes a device when FCM reports it as unregistered', function (): void {
    Http::fake([
        'fcm.googleapis.com/*' => Http::response([
            'error' => ['status' => 'UNREGISTERED', 'message' => 'Requested entity was not found.'],
        ], 404),
    ]);

    $userId = Fixtures::userId();
    $device = UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'dead-token']);

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    $device->refresh();
    expect($device->revoked_at)->not->toBeNull()
        ->and($device->push_token)->toBeNull();
});

it('does not revoke a device on a retryable server error', function (): void {
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
    ]);

    $userId = Fixtures::userId();
    $device = UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    $device->refresh();
    expect($device->revoked_at)->toBeNull()
        ->and($device->push_token)->toBe('token-1');
});

it('never fails the in-app gateway even if the push HTTP call throws', function (): void {
    // FcmSender لا يغلّف نداء Http::post نفسه بـ try/catch (فقط نداء
    // التوكن) — عمدًا، عشان هذا الاختبار يتحقق إن الحارس الحقيقي ضد فشل
    // push هو في InAppChannelGateway::send، لا في FcmSender.
    Http::fake(function (): never {
        throw new ConnectionException('network unreachable');
    });

    $userId = Fixtures::userId();
    UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);

    $result = app(InAppChannelGateway::class)->send(makeGatewayMessage($userId));

    expect($result->isAccepted())->toBeTrue();
});

it('revokes only the device FCM rejects, and still sends to the others', function (): void {
    Http::fake(function ($request) {
        $token = $request->data()['message']['token'];

        return $token === 'dead-token'
            ? Http::response(['error' => ['status' => 'UNREGISTERED']], 404)
            : Http::response(['name' => 'ok'], 200);
    });

    $userId = Fixtures::userId();
    $deadDevice = UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'dead-token']);
    $liveDevice = UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'live-token']);

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    $deadDevice->refresh();
    $liveDevice->refresh();

    expect($deadDevice->revoked_at)->not->toBeNull()
        ->and($deadDevice->push_token)->toBeNull()
        ->and($liveDevice->revoked_at)->toBeNull()
        ->and($liveDevice->push_token)->toBe('live-token');

    Http::assertSentCount(2);
});

it('fails safely without crashing when the credentials file does not exist', function (): void {
    config()->set('notifications.channels.push.credentials_path', '/nonexistent/path.json');
    $this->app->bind(FirebaseAccessTokenProvider::class, ServiceAccountAccessTokenProvider::class);
    Http::fake();

    $userId = Fixtures::userId();
    $device = UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    // فشل قراءة الاعتماد لا يوقّع أي طلب FCM ولا يستثني، ولا يُعامل الجهاز
    // كمرفوض دائمًا (retryable)، فلا يُسحب.
    Http::assertNothingSent();
    $device->refresh();
    expect($device->revoked_at)->toBeNull();
});

it('fails safely without crashing when the credentials file has invalid JSON', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'fcm-bad-creds-');
    file_put_contents($path, 'not valid json');
    config()->set('notifications.channels.push.credentials_path', $path);
    $this->app->bind(FirebaseAccessTokenProvider::class, ServiceAccountAccessTokenProvider::class);
    Http::fake();

    $userId = Fixtures::userId();
    UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);

    app(PushMirrorDispatcher::class)->dispatch(makeGatewayMessage($userId));

    Http::assertNothingSent();
    unlink($path);
});

it('falls back to the app name when the notification has no subject', function (): void {
    Http::fake([
        'fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200),
    ]);

    $userId = Fixtures::userId();
    UserDevice::factory()->create(['user_id' => $userId, 'push_token' => 'token-1']);

    app(PushMirrorDispatcher::class)->dispatch(
        makeGatewayMessage($userId, subject: [], body: ['ar' => 'نص فقط']),
    );

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['message']['notification']['title'] === config('app.name')
            && $body['message']['notification']['body'] === 'نص فقط';
    });
});
