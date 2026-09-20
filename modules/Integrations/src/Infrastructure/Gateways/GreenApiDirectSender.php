<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Domain\Contracts\WhatsAppDirectSender;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Throwable;

/**
 * إرسال مباشر عبر Green API: نص، أو ملف مرفوع مع تعليق اختياري.
 *
 * الرقم يصل هنا مطبَّعًا بصيغة E.164 — التطبيع مسؤولية من يبني القائمة، لأن
 * قواعده تختلف باختلاف المصدر، ورقم مرفوض يجب أن يظهر للمرسِل قبل الإرسال لا
 * أن يُكتشف عند البوابة.
 */
final readonly class GreenApiDirectSender implements WhatsAppDirectSender
{
    public function __construct(
        private Factory $http,
        private GreenApiConnections $connections,
    ) {}

    public function sendText(string $organizationId, string $phone, string $text): GatewayResult
    {
        $configuration = $this->configuration($organizationId);

        if ($configuration === null) {
            return $this->permanentFailure('whatsapp_configuration_invalid');
        }

        if (trim($text) === '') {
            return $this->permanentFailure('whatsapp_body_empty');
        }

        $limit = (int) config('notifications.channels.whatsapp.green_api.max_message_length', 20000);
        $text = mb_strlen($text) > $limit ? trim(mb_substr($text, 0, $limit)) : $text;

        return $this->dispatch(
            fn (PendingRequest $request, string $base): Response => $request
                ->asJson()
                ->post($this->endpoint($base, $configuration, 'sendMessage'), [
                    'chatId' => $this->chatId($phone),
                    'message' => $text,
                ]),
            $configuration,
            $configuration['api_url'],
        );
    }

    public function sendFile(
        string $organizationId,
        string $phone,
        $contents,
        string $fileName,
        ?string $caption = null,
    ): GatewayResult {
        $configuration = $this->configuration($organizationId);

        if ($configuration === null) {
            return $this->permanentFailure('whatsapp_configuration_invalid');
        }

        if (!is_resource($contents)) {
            return $this->permanentFailure('whatsapp_media_unreadable');
        }

        $fields = [
            ['name' => 'chatId', 'contents' => $this->chatId($phone)],
            ['name' => 'fileName', 'contents' => $fileName],
        ];

        if ($caption !== null && trim($caption) !== '') {
            // حد التعليق عند المزوّد أقصر من حد الرسالة النصية.
            $fields[] = ['name' => 'caption', 'contents' => mb_substr(trim($caption), 0, 1000)];
        }

        return $this->dispatch(
            fn (PendingRequest $request, string $base): Response => $request
                ->attach('file', $contents, $fileName)
                ->post($this->endpoint($base, $configuration, 'sendFileByUpload'), $fields),
            $configuration,
            $configuration['media_url'],
        );
    }

    /**
     * @param callable(PendingRequest, string): Response $call
     * @param array{api_url: string, media_url: string, instance_id: string, token: string, timeout_seconds: int, retry_delays_milliseconds: list<int>} $configuration
     */
    private function dispatch(callable $call, array $configuration, string $base): GatewayResult
    {
        $request = $this->http
            ->acceptJson()
            ->timeout($configuration['timeout_seconds'])
            ->retry(
                $configuration['retry_delays_milliseconds'],
                0,
                fn (Throwable $error, PendingRequest $_request, ?string $_method): bool => $this->shouldRetry($error),
                throw: false,
            );

        try {
            $response = $call($request, $base);
        } catch (ConnectionException) {
            return $this->retryableFailure('whatsapp_network_error');
        } catch (Throwable $error) {
            return $this->permanentFailure($error->getMessage());
        }

        if (!$response->successful()) {
            return $this->failedResponse($response);
        }

        $externalMessageId = $response->json('idMessage');

        if (!is_string($externalMessageId) || trim($externalMessageId) === '') {
            return $this->retryableFailure('whatsapp_provider_response_invalid', [
                'http_status' => $response->status(),
            ]);
        }

        return GatewayResult::accepted([
            'provider' => 'green_api',
            'external_message_id' => $externalMessageId,
            'status' => 'accepted',
            'http_status' => $response->status(),
        ]);
    }

    /**
     * @param array{instance_id: string, token: string} $configuration
     */
    private function endpoint(string $base, array $configuration, string $method): string
    {
        return sprintf(
            '%s/waInstance%s/%s/%s',
            rtrim($base, '/'),
            $configuration['instance_id'],
            $method,
            $configuration['token'],
        );
    }

    /**
     * @return array{
     *     api_url: string,
     *     media_url: string,
     *     instance_id: string,
     *     token: string,
     *     timeout_seconds: positive-int,
     *     retry_delays_milliseconds: list<int>
     * }|null
     */
    private function configuration(string $organizationId): ?array
    {
        $stored = $this->connections->credentials($organizationId);

        if ($stored === null) {
            return null;
        }

        $settings = (array) config('notifications.channels.whatsapp.green_api', []);
        $timeout = $settings['timeout_seconds'] ?? null;
        $delays = $settings['retry_delays_milliseconds'] ?? null;

        if (!is_int($timeout) || $timeout < 1 || !is_array($delays) || !array_is_list($delays)) {
            return null;
        }

        foreach ($delays as $delay) {
            if (!is_int($delay) || $delay < 0) {
                return null;
            }
        }

        return [
            ...$stored,
            'media_url' => $this->mediaUrl((string) $stored['api_url'], $settings),
            'timeout_seconds' => $timeout,
            'retry_delays_milliseconds' => $delays,
        ];
    }

    /**
     * عنوان رفع الوسائط عند Green API مضيف مستقل عن عنوان الـAPI في سحابتهم
     * العامة؛ إرسال ملف إلى api.green-api.com يُرفض. النشر الخاص يستخدم نفس
     * العنوان، فالقيمة قابلة للضبط ولا تُفرض.
     *
     * @param array<string, mixed> $settings
     */
    private function mediaUrl(string $apiUrl, array $settings): string
    {
        $configured = $settings['media_url'] ?? null;

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $host = parse_url($apiUrl, PHP_URL_HOST);

        return is_string($host) && str_ends_with($host, 'green-api.com')
            ? 'https://media.green-api.com'
            : $apiUrl;
    }

    private function chatId(string $phone): string
    {
        return ltrim($phone, '+').'@c.us';
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
