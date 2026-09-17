<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Infrastructure\Gateways\GreenApiGateway;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Shared\Testing\Fixtures;

function whatsappMessage(array $body, array $payload = []): GatewayMessage
{
    return new GatewayMessage(
        messageId: (string) Str::ulid(),
        organizationId: Fixtures::organizationId(),
        recipientId: Fixtures::userId(),
        category: 'session_changed',
        channel: 'whatsapp',
        locale: 'ar',
        eventName: 'postponement.scheduled',
        eventId: (string) Str::ulid(),
        correlationId: null,
        subject: null,
        body: $body,
        payload: ['phone' => '+201000000000', 'phone_country' => 'EG', ...$payload],
    );
}

function emptyBodyActivateWhatsapp(): void
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
        'status' => ConnectionStatus::Active,
    ]);
    $connection->settings = [
        'api_url' => 'https://7107.api.greenapi.com',
        'instance_id' => '1234567890',
        'state' => 'authorized',
    ];
    $connection->credentials = ['token' => 'abc123token', 'webhook_token' => Str::random(48)];
    $connection->activated_at = now('UTC');
    $connection->save();
}

/**
 * صفٌّ بلا قالب مطابق يخرج متنه فارغًا. الوسم الآلي جعل المجموع غير فارغ،
 * فوصلت للمستلم رسالة لا تحمل إلا سطر «رسالة تلقائية» — 17 سبتمبر 2026.
 */
it('never sends a message that is nothing but the automatic notice', function (): void {
    $gateway = app(GreenApiGateway::class);
    $text = new ReflectionMethod($gateway, 'text');

    expect($text->invoke($gateway, whatsappMessage([])))->toBe('')
        ->and($text->invoke($gateway, whatsappMessage(['ar' => '   '])))->toBe('');
});

it('refuses to deliver an empty body instead of dressing it as a notice', function (): void {
    // بلا اتصال فعّال يتوقف الإرسال عند فحص الإعداد قبل أن يصل إلى المتن.
    emptyBodyActivateWhatsapp();

    $result = app(GreenApiGateway::class)->send(whatsappMessage([]));

    expect($result->isAccepted())->toBeFalse()
        ->and($result->error())->toBe('whatsapp_body_empty');
});

it('still stamps the notice on a message that has real content', function (): void {
    $gateway = app(GreenApiGateway::class);
    $text = new ReflectionMethod($gateway, 'text');
    $rendered = $text->invoke($gateway, whatsappMessage(['ar' => 'تم تأجيل الحصة']));

    expect($rendered)->toStartWith('تم تأجيل الحصة')
        ->and($rendered)->toContain((string) __('integrations::whatsapp.automatic_notice'));
});

it('has a template for every postponement event so none of them ships empty', function (): void {
    foreach (['postponement.requested', 'postponement.scheduled'] as $eventKey) {
        $template = NotificationTemplate::query()
            ->whereNull('organization_id')
            ->where('event_key', $eventKey)
            ->where('channel', 'whatsapp')
            ->where('locale', 'ar')
            ->first();

        expect($template)->not->toBeNull("missing template for {$eventKey}")
            ->and(trim((string) $template->body))->not->toBe('');
    }
});

it('leaves no configured event without a template in the default locale', function (): void {
    $configured = array_keys((array) config('notifications.events', []));
    $withText = array_keys((array) Lang::get('notifications::templates', [], 'ar'));
    $stored = NotificationTemplate::query()
        ->whereNull('organization_id')
        ->where('locale', 'ar')
        ->where('channel', 'whatsapp')
        ->pluck('event_key')
        ->all();

    // حدث له نص في ملف اللغة يجب أن يكون له صف في الجدول — الجدول هو المصدر.
    $missing = array_values(array_diff(array_intersect($configured, $withText), $stored));

    expect($missing)->toBe([]);
});

it('lets schedule churn wait for quiet hours instead of overriding them', function (): void {
    foreach (['session_changed', 'postponement_request', 'schedule_change_request'] as $category) {
        expect(config("notifications.categories.{$category}.critical"))->toBeFalse()
            ->and(config("notifications.categories.{$category}.respects_quiet_hours"))->toBeTrue();
    }
});

it('renders the postponement times in the recipient timezone', function (): void {
    $parameters = (array) config('notifications.localization.datetime_parameters');

    expect($parameters)->toContain('proposed_start')
        ->and($parameters)->toContain('agreed_start');
});
