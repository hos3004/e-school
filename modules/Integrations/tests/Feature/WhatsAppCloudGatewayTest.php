<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Infrastructure\Gateways\WhatsAppCloudGateway;

beforeEach(function (): void {
    config([
        'notifications.channels.whatsapp' => [
            'enabled' => true,
            'token' => 'test-token',
            'phone_number_id' => '1234567890',
            'api_version' => 'v23.0',
            'timeout_seconds' => 5,
            'retry_delays_milliseconds' => [],
        ],
    ]);
});

it('sends a localized Meta template with ordered text parameters', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [[
                'id' => 'wamid.test-message',
                'message_status' => 'accepted',
            ]],
        ]),
    ]);

    $result = app(WhatsAppCloudGateway::class)->send(whatsAppGatewayMessage());

    expect($result->isAccepted())->toBeTrue()
        ->and($result->providerResponse())->toMatchArray([
            'external_message_id' => 'wamid.test-message',
            'status' => 'accepted',
            'http_status' => 200,
        ]);

    Http::assertSent(function (Request $request): bool {
        expect($request->url())
            ->toBe('https://graph.facebook.com/v23.0/1234567890/messages')
            ->and($request->hasHeader('Authorization', 'Bearer test-token'))->toBeTrue()
            ->and($request->data())->toBe([
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => '+201001234567',
                'type' => 'template',
                'template' => [
                    'name' => 'session_scheduled_ar',
                    'language' => ['code' => 'ar'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => 'الرياضيات'],
                            ['type' => 'text', 'text' => '2026-08-23 09:00'],
                        ],
                    ]],
                ],
            ]);

        return true;
    });
});

it('classifies an exhausted Meta 429 response as retryable', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => [
                'code' => 80007,
                'message' => 'Rate limit reached',
            ],
        ], 429),
    ]);

    $result = app(WhatsAppCloudGateway::class)->send(whatsAppGatewayMessage());

    expect($result->isAccepted())->toBeFalse()
        ->and($result->isRetryable())->toBeTrue()
        ->and($result->error())->toBe('80007: Rate limit reached')
        ->and($result->providerResponse())->toMatchArray([
            'status' => 'failed',
            'failure_reason' => '80007: Rate limit reached',
            'provider_error_code' => 80007,
            'http_status' => 429,
        ]);
});

it('classifies a Meta 400 template rejection as permanent', function (): void {
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'error' => [
                'code' => 132001,
                'message' => 'Template does not exist',
            ],
        ], 400),
    ]);

    $result = app(WhatsAppCloudGateway::class)->send(whatsAppGatewayMessage());

    expect($result->isAccepted())->toBeFalse()
        ->and($result->isRetryable())->toBeFalse()
        ->and($result->providerResponse())->toMatchArray([
            'status' => 'failed',
            'failure_reason' => '132001: Template does not exist',
            'http_status' => 400,
        ]);
});

it('uses configured retry delays for a transient provider failure', function (): void {
    config(['notifications.channels.whatsapp.retry_delays_milliseconds' => [0]]);
    Http::fakeSequence()
        ->push(['error' => ['code' => 1, 'message' => 'Temporary']], 500)
        ->push(['messages' => [['id' => 'wamid.after-retry']]], 200);

    $result = app(WhatsAppCloudGateway::class)->send(whatsAppGatewayMessage());

    expect($result->isAccepted())->toBeTrue()
        ->and($result->providerResponse()['external_message_id'])->toBe('wamid.after-retry');
    Http::assertSentCount(2);
});

it('rejects an invalid recipient permanently without contacting Meta', function (): void {
    Http::fake();
    $message = whatsAppGatewayMessage([
        'phone' => 'invalid-number',
    ]);

    $result = app(WhatsAppCloudGateway::class)->send($message);

    expect($result->isAccepted())->toBeFalse()
        ->and($result->isRetryable())->toBeFalse()
        ->and($result->error())->toBe('invalid_phone_number');
    Http::assertNothingSent();
});

/**
 * @param array<string, mixed> $payload
 */
function whatsAppGatewayMessage(array $payload = []): GatewayMessage
{
    return new GatewayMessage(
        messageId: 'outbox-1',
        organizationId: 'organization-1',
        recipientId: 'user-1',
        category: 'session_changed',
        channel: 'whatsapp',
        locale: 'ar',
        eventName: 'session.scheduled',
        eventId: 'event-1',
        correlationId: 'correlation-1',
        subject: null,
        body: [],
        payload: [
            'phone' => '01001234567',
            'phone_country' => 'EG',
            'provider_template_name' => 'session_scheduled_ar',
            'template_parameters' => ['الرياضيات', '2026-08-23 09:00'],
            ...$payload,
        ],
    );
}
