<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Integrations\Infrastructure\Gateways\GreenApiGateway;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/*
 * خط واتساب عبر Green API — النص الحر.
 *
 * الفرق الجوهري عن مسار Meta: لا اسم قالب عند المزوّد ولا ترتيب بارامترات،
 * بل النص المركّب من قوالب المنصة نفسها. لذلك تتحقق هذه الاختبارات من أن
 * النص الواصل للمزوّد هو نص القالب بعد حقن القيم، وأن chatId مشتق من رقم
 * مطبَّع بصيغة E.164، وأن تصنيف الفشل يفرّق بين الدائم والقابل للإعادة.
 */
beforeEach(function (): void {
    /** @var TestCase $this */
    $this->seed(NotificationTemplateSeeder::class);

    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => false],
            'email' => ['enabled' => false],
            'whatsapp' => [
                'enabled' => true,
                'provider' => 'green_api',
                'gateway' => GreenApiGateway::class,
                'green_api' => [
                    'api_url' => 'https://api.green-api.com',
                    'instance_id' => '1101234567',
                    'token' => 'testtoken123',
                    'timeout_seconds' => 5,
                    'retry_delays_milliseconds' => [],
                ],
            ],
        ],
        'notifications.categories.session_changed' => [
            'channels' => ['whatsapp'],
            'critical' => true,
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);
});

function greenApiRecipient(): string
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

function dispatchGreenApiNotification(): NotificationOutbox
{
    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [greenApiRecipient()],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    return NotificationOutbox::query()->sole();
}

it('sends the rendered template as free text and stores the provider message id', function (): void {
    /** @var TestCase $this */
    Http::fake([
        'api.green-api.com/*' => Http::response(['idMessage' => 'BAE5367236B3B0F1']),
    ]);

    $outbox = dispatchGreenApiNotification();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);
    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and($outbox->external_message_id)->toBe('BAE5367236B3B0F1')
        ->and($outbox->provider_status)->toBe('accepted')
        ->and($outbox->failure_reason)->toBeNull()
        ->and($attempt->succeeded)->toBeTrue()
        ->and($attempt->external_message_id)->toBe('BAE5367236B3B0F1');

    Http::assertSent(function (Request $request): bool {
        $message = (string) $request['message'];

        return $request->url() === 'https://api.green-api.com/waInstance1101234567/sendMessage/testtoken123'
            // الرقم المخزَّن محلي، وchatId يجب أن يبنى على E.164 بلا علامة +.
            && $request['chatId'] === '201001234567@c.us'
            // العنوان أولًا ثم المتن — والمتن يحمل قيمة الحدث بعد الحقن.
            && str_contains($message, '*تمت جدولة حصة*')
            && str_contains($message, 'تمت جدولة الحصة لتبدأ في')
            && !str_contains($message, '{{scheduled_start}}');
    });
});

it('treats a rejected provider response as a permanent failure', function (): void {
    /** @var TestCase $this */
    Bus::fake([SendQueuedNotification::class]);
    Http::fake([
        'api.green-api.com/*' => Http::response(['message' => 'instance not authorized'], 401),
    ]);

    $outbox = dispatchGreenApiNotification();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);
    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($outbox->last_error_retryable)->toBeFalse()
        ->and($outbox->failure_reason)->toBe('instance not authorized')
        ->and($attempt->retryable)->toBeFalse();

    Bus::assertNotDispatched(SendQueuedNotification::class);
});

it('keeps a quota rejection retryable so the message is not lost', function (): void {
    /** @var TestCase $this */
    Http::fake([
        'api.green-api.com/*' => Http::response(['message' => 'monthly quota exceeded'], 466),
    ]);

    $outbox = dispatchGreenApiNotification();
    app()->call([new SendQueuedNotification($outbox->id), 'handle']);
    $attempt = NotificationDeliveryAttempt::query()->sole();

    expect($attempt->retryable)->toBeTrue()
        ->and($outbox->refresh()->status)->not->toBe(OutboxStatus::Sent);
});

it('never calls the provider when the channel is switched off', function (): void {
    /** @var TestCase $this */
    Http::fake();
    config(['notifications.channels.whatsapp.enabled' => false]);

    // القيد مكتوب سلفًا؛ الإطفاء بعده يجب أن يمنع التسليم لا أن يتجاهله.
    config(['notifications.channels.whatsapp.enabled' => true]);
    $outbox = dispatchGreenApiNotification();
    config(['notifications.channels.whatsapp.enabled' => false]);

    app()->call([new SendQueuedNotification($outbox->id), 'handle']);

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($outbox->failure_reason)->toBe('whatsapp_configuration_invalid');

    Http::assertNothingSent();
});
