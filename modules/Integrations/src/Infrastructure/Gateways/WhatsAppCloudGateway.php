<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Throwable;

/**
 * يسلّم قوالب Meta المعتمدة فقط؛ النص الحر غير مسموح خارج نافذة المحادثة.
 */
final readonly class WhatsAppCloudGateway implements ChannelGateway
{
    private const API_BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private Factory $http,
        private PhoneNumberNormalizer $phoneNumbers,
    ) {}

    public function send(GatewayMessage $message): GatewayResult
    {
        $configuration = $this->configuration();

        if ($configuration === null) {
            return $this->permanentFailure('whatsapp_configuration_invalid');
        }

        $payload = $this->payload($message);

        if ($payload instanceof GatewayResult) {
            return $payload;
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            self::API_BASE_URL,
            $configuration['api_version'],
            $configuration['phone_number_id'],
        );

        try {
            $response = $this->http
                ->withToken($configuration['token'])
                ->acceptJson()
                ->asJson()
                ->timeout($configuration['timeout_seconds'])
                ->retry(
                    $configuration['retry_delays_milliseconds'],
                    0,
                    fn (Throwable $error, PendingRequest $_request, ?string $_method): bool => $this->shouldRetry($error),
                    throw: false,
                )
                ->post($url, $payload);
        } catch (ConnectionException) {
            return $this->retryableFailure('whatsapp_network_error');
        }

        if (!$response->successful()) {
            return $this->failedResponse($response);
        }

        $externalMessageId = $response->json('messages.0.id');

        if (!is_string($externalMessageId) || trim($externalMessageId) === '') {
            return $this->retryableFailure(
                'whatsapp_provider_response_invalid',
                ['http_status' => $response->status()],
            );
        }

        $providerStatus = $response->json('messages.0.message_status');

        return GatewayResult::accepted([
            'external_message_id' => $externalMessageId,
            'status' => is_string($providerStatus) && $providerStatus !== ''
                ? $providerStatus
                : 'accepted',
            'http_status' => $response->status(),
        ]);
    }

    /**
     * @return array{
     *     token: non-empty-string,
     *     phone_number_id: non-empty-string,
     *     api_version: non-empty-string,
     *     timeout_seconds: positive-int,
     *     retry_delays_milliseconds: list<int>
     * }|null
     */
    private function configuration(): ?array
    {
        $configuration = config('notifications.channels.whatsapp');

        if (!is_array($configuration) || ($configuration['enabled'] ?? false) !== true) {
            return null;
        }

        $token = $configuration['token'] ?? null;
        $phoneNumberId = $configuration['phone_number_id'] ?? null;
        $apiVersion = $configuration['api_version'] ?? null;
        $timeoutSeconds = $configuration['timeout_seconds'] ?? null;
        $retryDelays = $configuration['retry_delays_milliseconds'] ?? null;

        if (
            !is_string($token) || trim($token) === ''
            || !is_string($phoneNumberId) || preg_match('/^\d+$/', $phoneNumberId) !== 1
            || !is_string($apiVersion) || preg_match('/^v\d+\.\d+$/', $apiVersion) !== 1
            || !is_int($timeoutSeconds) || $timeoutSeconds < 1
            || !is_array($retryDelays) || !array_is_list($retryDelays)
        ) {
            return null;
        }

        foreach ($retryDelays as $delay) {
            if (!is_int($delay) || $delay < 0) {
                return null;
            }
        }

        return [
            'token' => trim($token),
            'phone_number_id' => $phoneNumberId,
            'api_version' => $apiVersion,
            'timeout_seconds' => $timeoutSeconds,
            'retry_delays_milliseconds' => $retryDelays,
        ];
    }

    /**
     * @return array<string, mixed>|GatewayResult
     */
    private function payload(GatewayMessage $message): array|GatewayResult
    {
        $phone = $message->payload['phone'] ?? null;
        $phoneCountry = $message->payload['phone_country'] ?? null;
        $templateName = $message->payload['provider_template_name'] ?? null;
        $parameters = $message->payload['template_parameters'] ?? null;

        if (
            !is_string($phone)
            || (!is_string($phoneCountry) && $phoneCountry !== null)
            || !is_string($templateName)
            || preg_match('/^[a-z0-9_]+$/', $templateName) !== 1
            || !is_array($parameters)
            || !array_is_list($parameters)
        ) {
            return $this->permanentFailure('whatsapp_payload_invalid');
        }

        foreach ($parameters as $parameter) {
            if (!is_string($parameter)) {
                return $this->permanentFailure('whatsapp_template_parameters_invalid');
            }
        }

        $locale = str_replace('-', '_', trim($message->locale));

        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
            return $this->permanentFailure('whatsapp_template_locale_invalid');
        }

        try {
            $recipient = $this->phoneNumbers->normalize($phone, $phoneCountry);
        } catch (InvalidArgumentException $error) {
            return $this->permanentFailure($error->getMessage());
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $locale],
        ];

        if ($parameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $parameter): array => [
                        'type' => 'text',
                        'text' => $parameter,
                    ],
                    $parameters,
                ),
            ]];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => $template,
        ];
    }

    private function shouldRetry(Throwable $error): bool
    {
        if ($error instanceof ConnectionException) {
            return true;
        }

        if (!$error instanceof RequestException) {
            return false;
        }

        $status = $error->response->status();

        return $status === 429 || $status >= 500;
    }

    private function failedResponse(Response $response): GatewayResult
    {
        $providerCode = $response->json('error.code');
        $providerMessage = $response->json('error.message');
        $reasonParts = [];

        if (is_int($providerCode) || is_string($providerCode)) {
            $reasonParts[] = (string) $providerCode;
        }

        if (is_string($providerMessage) && trim($providerMessage) !== '') {
            $reasonParts[] = trim($providerMessage);
        }

        $failureReason = $reasonParts !== []
            ? implode(': ', $reasonParts)
            : 'whatsapp_provider_error';
        $providerResponse = [
            'status' => 'failed',
            'failure_reason' => $failureReason,
            'http_status' => $response->status(),
        ];

        if (is_int($providerCode) || is_string($providerCode)) {
            $providerResponse['provider_error_code'] = $providerCode;
        }

        return GatewayResult::rejected(
            $failureReason,
            $response->status() === 429 || $response->serverError(),
            $providerResponse,
        );
    }

    /**
     * @param array<string, mixed> $providerResponse
     */
    private function permanentFailure(string $reason, array $providerResponse = []): GatewayResult
    {
        return GatewayResult::rejected($reason, false, [
            'status' => 'failed',
            'failure_reason' => $reason,
            ...$providerResponse,
        ]);
    }

    /**
     * @param array<string, mixed> $providerResponse
     */
    private function retryableFailure(string $reason, array $providerResponse = []): GatewayResult
    {
        return GatewayResult::rejected($reason, true, [
            'status' => 'failed',
            'failure_reason' => $reason,
            ...$providerResponse,
        ]);
    }
}
