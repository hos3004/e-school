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
 * بوابة واتساب عبر Green API.
 *
 * تختلف عن بوابة Meta في نقطة جوهرية واحدة: Green API يسلّم نصًا حرًا، فلا
 * يحتاج اسم قالب معتمدًا من المزوّد ولا يفرض ترتيب بارامترات. لذلك تأخذ هذه
 * البوابة النص المركّب مسبقًا من صندوق الصادر (subject + body بلغة الصف)
 * وترسله كما هو؛ التقييد الوحيد الباقي هو الحد الأقصى لطول رسالة واتساب.
 *
 * كل الأسرار والمهل وعناوين المزوّد تُقرأ من
 * config('notifications.channels.whatsapp.green_api') — لا قيمة واحدة في الكود.
 */
final readonly class GreenApiGateway implements ChannelGateway
{
    /**
     * حد واتساب لرسالة نصية واحدة. الأطول يُقصّ بدل أن يرفضه المزوّد كاملًا.
     */
    private const MAX_MESSAGE_LENGTH = 4096;

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
            '%s/waInstance%s/sendMessage/%s',
            $configuration['api_url'],
            $configuration['instance_id'],
            $configuration['token'],
        );

        try {
            $response = $this->http
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

        $externalMessageId = $response->json('idMessage');

        if (!is_string($externalMessageId) || trim($externalMessageId) === '') {
            return $this->retryableFailure(
                'whatsapp_provider_response_invalid',
                ['http_status' => $response->status()],
            );
        }

        return GatewayResult::accepted([
            'provider' => 'green_api',
            'external_message_id' => $externalMessageId,
            'status' => 'accepted',
            'http_status' => $response->status(),
        ]);
    }

    /**
     * @return array{
     *     api_url: non-empty-string,
     *     instance_id: non-empty-string,
     *     token: non-empty-string,
     *     timeout_seconds: positive-int,
     *     retry_delays_milliseconds: list<int>
     * }|null
     */
    private function configuration(): ?array
    {
        $channel = config('notifications.channels.whatsapp');

        if (!is_array($channel) || ($channel['enabled'] ?? false) !== true) {
            return null;
        }

        $configuration = $channel['green_api'] ?? null;

        if (!is_array($configuration)) {
            return null;
        }

        $apiUrl = $configuration['api_url'] ?? null;
        $instanceId = $configuration['instance_id'] ?? null;
        $token = $configuration['token'] ?? null;
        $timeoutSeconds = $configuration['timeout_seconds'] ?? null;
        $retryDelays = $configuration['retry_delays_milliseconds'] ?? null;

        if (
            !is_string($apiUrl) || preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?$#', rtrim($apiUrl, '/')) !== 1
            || !is_string($instanceId) || preg_match('/^\d+$/', $instanceId) !== 1
            || !is_string($token) || preg_match('/^[A-Za-z0-9]+$/', $token) !== 1
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
            'api_url' => rtrim($apiUrl, '/'),
            'instance_id' => $instanceId,
            'token' => $token,
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

        if (!is_string($phone) || (!is_string($phoneCountry) && $phoneCountry !== null)) {
            return $this->permanentFailure('whatsapp_payload_invalid');
        }

        $text = $this->text($message);

        if ($text === '') {
            return $this->permanentFailure('whatsapp_body_empty');
        }

        try {
            $recipient = $this->phoneNumbers->normalize($phone, $phoneCountry);
        } catch (InvalidArgumentException $error) {
            return $this->permanentFailure($error->getMessage());
        }

        return [
            'chatId' => ltrim($recipient, '+').'@c.us',
            'message' => $text,
        ];
    }

    /**
     * نص الرسالة = عنوان الصف (إن وُجد واختلف) ثم متنه، بلغة الصف نفسها.
     *
     * صندوق الصادر يخزّن الاثنين كخريطة لغة => نص، فنأخذ لغة الصف أولًا ثم
     * أول قيمة متاحة حتى لا تسقط رسالة بسبب اختلاف مفتاح اللغة.
     */
    private function text(GatewayMessage $message): string
    {
        $body = $this->localized($message->body, $message->locale);
        $subject = $this->localized($message->subject ?? [], $message->locale);
        $text = $subject !== '' && $subject !== $body
            ? '*'.$subject.'*'.PHP_EOL.PHP_EOL.$body
            : $body;
        $text = trim($text);

        return mb_strlen($text) > self::MAX_MESSAGE_LENGTH
            ? mb_substr($text, 0, self::MAX_MESSAGE_LENGTH)
            : $text;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function localized(array $values, string $locale): string
    {
        $value = $values[$locale] ?? null;

        if (!is_string($value) || trim($value) === '') {
            foreach ($values as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $value = $candidate;
                    break;
                }
            }
        }

        return is_string($value) ? trim($value) : '';
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
        $providerMessage = $response->json('message');
        $failureReason = is_string($providerMessage) && trim($providerMessage) !== ''
            ? trim($providerMessage)
            : 'whatsapp_provider_error';

        // 466 = تجاوز حصة المثيل عند Green API؛ قابل للإعادة بعد تجدد الحصة.
        $retryable = $response->status() === 429
            || $response->status() === 466
            || $response->serverError();

        return GatewayResult::rejected($failureReason, $retryable, [
            'provider' => 'green_api',
            'status' => 'failed',
            'failure_reason' => $failureReason,
            'http_status' => $response->status(),
        ]);
    }

    /**
     * @param array<string, mixed> $providerResponse
     */
    private function permanentFailure(string $reason, array $providerResponse = []): GatewayResult
    {
        return GatewayResult::rejected($reason, false, [
            'provider' => 'green_api',
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
            'provider' => 'green_api',
            'status' => 'failed',
            'failure_reason' => $reason,
            ...$providerResponse,
        ]);
    }
}
