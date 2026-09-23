<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Gateways;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Integrations\Domain\Contracts\LlmGateway;
use Modules\Integrations\Domain\ValueObjects\LlmRequest;
use Modules\Integrations\Domain\ValueObjects\LlmResult;
use Throwable;

/**
 * بوابة Anthropic Messages API.
 *
 * ثلاثة قرارات تميّز هذه البوابة عن بقية بوابات المشروع:
 *
 *  1. قاطع دارة (circuit breaker). كل التكاملات الأخرى تعمل في الخلفية، فتأخّر
 *     المزوّد يؤخّر وظيفة لا إنسانًا. هذا النداء يحدث والمستخدم ينتظر أمام
 *     الشاشة، فمزوّد متعثّر يحبس طلبات المستخدمين واحدًا تلو الآخر. القاطع
 *     يفتح بعد عدد فشل متتالٍ فيعيد الرفض فورًا بلا انتظار، ويغلق بعد مهلة.
 *
 *  2. لا إعادة محاولة على 429. الحد عند المزوّد يعني أن الضغط قائم، وإعادة
 *     المحاولة داخل طلب متزامن تضاعف زمن انتظار المستخدم لتفشل غالبًا. تُصنَّف
 *     قابلة للإعادة ليقرر المستهلك، ولا تُعاد هنا.
 *
 *  3. لا logging للمحتوى. لا prompt ولا رد ولا رسالة مستخدم تدخل أي log.
 */
final readonly class AnthropicGateway implements LlmGateway
{
    public function __construct(
        private Factory $http,
        private LlmConnections $connections,
        private CacheRepository $cache,
    ) {}

    public function complete(LlmRequest $request): LlmResult
    {
        if (!$this->connections->isEnabled($request->organizationId)) {
            return LlmResult::rejected('llm_disabled', false);
        }

        $credentials = $this->connections->credentials($request->organizationId);

        if ($credentials === null) {
            return LlmResult::rejected('llm_not_configured', false);
        }

        $settings = $this->settings();

        if ($settings === null) {
            return LlmResult::rejected('llm_configuration_invalid', false);
        }

        if ($this->circuitIsOpen($request->organizationId)) {
            return LlmResult::rejected('llm_circuit_open', true);
        }

        $startedAt = hrtime(true);

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'x-api-key' => $credentials['api_key'],
                    'anthropic-version' => $settings['api_version'],
                ])
                ->connectTimeout($settings['connect_timeout_seconds'])
                ->timeout($settings['timeout_seconds'])
                ->retry(
                    $settings['retry_delays_milliseconds'],
                    0,
                    fn (Throwable $error, PendingRequest $_request, ?string $_method): bool => $this->shouldRetry($error),
                    throw: false,
                )
                ->post(rtrim($credentials['base_url'], '/').'/v1/messages', $this->payload($request));
        } catch (ConnectionException) {
            $this->recordTransientFailure($request->organizationId);

            return LlmResult::rejected('llm_network_error', true, $request->model, 0, 0, $this->elapsed($startedAt));
        }

        $latency = $this->elapsed($startedAt);

        if (!$response->successful()) {
            return $this->failedResponse($response, $request->organizationId, $request->model, $latency);
        }

        $this->recordSuccess($request->organizationId);

        return $this->acceptedResponse($response, $request->model, $latency);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LlmRequest $request): array
    {
        $payload = [
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'system' => $request->system,
            'messages' => $request->messagesPayload(),
        ];

        if ($request->stopSequences !== []) {
            $payload['stop_sequences'] = array_values($request->stopSequences);
        }

        return $payload;
    }

    private function acceptedResponse(Response $response, string $model, int $latency): LlmResult
    {
        $text = $this->extractText($response);
        $usage = $response->json('usage');
        $inputTokens = is_array($usage) && is_int($usage['input_tokens'] ?? null) ? $usage['input_tokens'] : 0;
        $outputTokens = is_array($usage) && is_int($usage['output_tokens'] ?? null) ? $usage['output_tokens'] : 0;

        /*
         * رد ناجح بلا نص ليس نجاحًا. يحدث حين يتوقف التوليد فورًا، ولو مرّرناه
         * لوصلت للمستخدم فقاعة فارغة. يُصنَّف قابلًا للإعادة لأن المحاولة
         * التالية غالبًا تنجح.
         */
        if (trim($text) === '') {
            return LlmResult::rejected('llm_empty_response', true, $model, $inputTokens, $outputTokens, $latency);
        }

        $stopReason = $response->json('stop_reason');
        $returnedModel = $response->json('model');

        return LlmResult::accepted(
            trim($text),
            is_string($returnedModel) && $returnedModel !== '' ? $returnedModel : $model,
            $inputTokens,
            $outputTokens,
            $latency,
            is_string($stopReason) ? $stopReason : null,
        );
    }

    /**
     * النص = ضم كل مقاطع النوع text بالترتيب. المزوّد يعيد قائمة مقاطع، وقد
     * تحتوي أنواعًا أخرى نتجاهلها بدل أن نفترض مقطعًا واحدًا دائمًا.
     */
    private function extractText(Response $response): string
    {
        $content = $response->json('content');

        if (!is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $block) {
            if (!is_array($block) || ($block['type'] ?? null) !== 'text') {
                continue;
            }

            $value = $block['text'] ?? null;

            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return implode('', $parts);
    }

    private function failedResponse(Response $response, string $organizationId, string $model, int $latency): LlmResult
    {
        $status = $response->status();
        $providerType = $response->json('error.type');
        $reason = is_string($providerType) && trim($providerType) !== ''
            ? 'llm_'.trim($providerType)
            : 'llm_provider_error';

        // 429 حد المعدّل و529 إجهاد المزوّد: الطلب سليم والوقت وحده هو المشكلة.
        $retryable = $status === 429 || $status === 529 || $response->serverError();

        if ($retryable) {
            $this->recordTransientFailure($organizationId);
        }

        /*
         * 401/403 يعني مفتاحًا خاطئًا أو مسحوبًا. نسجّل الحالة لتظهر في اللوحة
         * ولا نغيّر حالة الاتصال إلى Expired: الأخيرة نهائية في آلة الحالات
         * وتحتاج تدخلًا يدويًا في القاعدة لفكّها، وهو ثمن باهظ لخطأ قد يكون
         * عابرًا (مفتاح دُوِّر للتو، أو خطأ مؤقت عند المزوّد).
         */
        if ($status === 401 || $status === 403) {
            $this->connections->recordState($organizationId, 'auth_failed');
        }

        return LlmResult::rejected($reason, $retryable, $model, 0, 0, $latency);
    }

    private function shouldRetry(Throwable $error): bool
    {
        if ($error instanceof ConnectionException) {
            return true;
        }

        if (!$error instanceof RequestException) {
            return false;
        }

        // 429 مستثناة عمدًا: انظر شرح الصنف.
        return $error->response->status() >= 500;
    }

    private function elapsed(float|int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * @return array{
     *     api_version: non-empty-string,
     *     timeout_seconds: positive-int,
     *     connect_timeout_seconds: positive-int,
     *     retry_delays_milliseconds: list<int>,
     *     circuit_failure_threshold: positive-int,
     *     circuit_open_seconds: positive-int
     * }|null
     */
    private function settings(): ?array
    {
        $settings = (array) config('llm.providers.anthropic', []);

        $apiVersion = $settings['api_version'] ?? null;
        $timeout = $settings['timeout_seconds'] ?? null;
        $connectTimeout = $settings['connect_timeout_seconds'] ?? null;
        $delays = $settings['retry_delays_milliseconds'] ?? null;
        $threshold = $settings['circuit_failure_threshold'] ?? null;
        $openSeconds = $settings['circuit_open_seconds'] ?? null;

        if (!is_string($apiVersion) || $apiVersion === ''
            || !is_int($timeout) || $timeout < 1
            || !is_int($connectTimeout) || $connectTimeout < 1
            || !is_int($threshold) || $threshold < 1
            || !is_int($openSeconds) || $openSeconds < 1
            || !is_array($delays) || !array_is_list($delays)
        ) {
            return null;
        }

        foreach ($delays as $delay) {
            if (!is_int($delay) || $delay < 0) {
                return null;
            }
        }

        return [
            'api_version' => $apiVersion,
            'timeout_seconds' => $timeout,
            'connect_timeout_seconds' => $connectTimeout,
            'retry_delays_milliseconds' => $delays,
            'circuit_failure_threshold' => $threshold,
            'circuit_open_seconds' => $openSeconds,
        ];
    }

    private function circuitIsOpen(string $organizationId): bool
    {
        return $this->cache->get($this->circuitKey($organizationId, 'open_until')) !== null;
    }

    private function recordTransientFailure(string $organizationId): void
    {
        $settings = $this->settings();

        if ($settings === null) {
            return;
        }

        $key = $this->circuitKey($organizationId, 'failures');
        $failures = (int) $this->cache->get($key, 0) + 1;

        $this->cache->put($key, $failures, $settings['circuit_open_seconds']);

        if ($failures >= $settings['circuit_failure_threshold']) {
            $this->cache->put($this->circuitKey($organizationId, 'open_until'), true, $settings['circuit_open_seconds']);
        }
    }

    private function recordSuccess(string $organizationId): void
    {
        $this->cache->forget($this->circuitKey($organizationId, 'failures'));
        $this->cache->forget($this->circuitKey($organizationId, 'open_until'));
    }

    /**
     * القاطع لكل مؤسسة: تعثّر مسار مؤسسة لا يجوز أن يوقف بوت مؤسسة أخرى سليمة،
     * ولا أن يمسح نجاحُ الأخرى عدّاد إخفاقات الأولى.
     */
    private function circuitKey(string $organizationId, string $suffix): string
    {
        return 'llm:anthropic:'.$organizationId.':'.$suffix;
    }
}
