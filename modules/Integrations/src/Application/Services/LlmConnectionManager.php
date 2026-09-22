<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * اتصال المؤسسة بمزوّد النموذج اللغوي.
 *
 * يتبع مدير Green API في كل شيء إلا نقطة واحدة مقصودة: **لا سقوط إلى إعداد
 * البيئة**. هناك، غياب صف الاتصال يعني الرجوع إلى مفتاح من .env؛ وهنا غيابه
 * يعني «مغلق». السبب أن هذا المزوّد يكلّف مالًا عند كل نداء، ومفتاح موجود في
 * البيئة لسبب قديم كان سيشغّل البوت لكل المستخدمين دون أن يفتحه أحد.
 *
 * الافتراض هنا مغلق دائمًا: لا صف = لا بوت.
 */
final readonly class LlmConnectionManager implements LlmConnections
{
    private const string PROVIDER_KEY = 'anthropic';

    public function __construct(private Factory $http, private AuditRecorder $audit) {}

    /** @return array<string, mixed> */
    public function view(string $organizationId): array
    {
        $connection = $this->connection($organizationId);
        $settings = $connection === null ? [] : ($connection->settings ?? []);
        $secrets = $connection === null ? [] : ($connection->credentials ?? []);

        $view = [
            'base_url' => (string) ($settings['base_url'] ?? config('llm.providers.anthropic.base_url', 'https://api.anthropic.com')),
            // المفتاح نفسه لا يغادر الخادم أبدًا — لا إلى اللوحة ولا إلى سجل التدقيق.
            'configured' => is_string($secrets['api_key'] ?? null) && $secrets['api_key'] !== '',
            'enabled' => $connection?->status === ConnectionStatus::Active,
            'state' => (string) ($settings['state'] ?? ''),
            'status' => $connection?->status->value ?? 'missing',
        ];

        $view['version'] = hash('sha256', json_encode([
            $connection?->id,
            $connection?->updated_at?->toISOString(),
            $view,
        ], JSON_THROW_ON_ERROR));

        return $view;
    }

    /** @return array{api_key: non-empty-string, base_url: non-empty-string}|null */
    public function credentials(string $organizationId): ?array
    {
        $connection = $this->connection($organizationId);

        if ($connection === null || $connection->status !== ConnectionStatus::Active) {
            return null;
        }

        $settings = $connection->settings ?? [];
        $secrets = $connection->credentials ?? [];

        $apiKey = $secrets['api_key'] ?? null;
        $baseUrl = $settings['base_url'] ?? config('llm.providers.anthropic.base_url');

        if (!is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        if (!is_string($baseUrl) || !$this->validUrl($baseUrl)) {
            return null;
        }

        return ['api_key' => trim($apiKey), 'base_url' => rtrim($baseUrl, '/')];
    }

    public function isEnabled(string $organizationId): bool
    {
        return $this->connection($organizationId)?->status === ConnectionStatus::Active;
    }

    public function save(string $organizationId, string $apiKey, string $baseUrl, string $actorId, string $reason): void
    {
        if (trim($apiKey) === '') {
            throw ValidationException::withMessages(['api_key' => __('support_bot::errors.api_key_required')]);
        }

        if (!$this->validUrl($baseUrl)) {
            throw ValidationException::withMessages(['base_url' => __('support_bot::errors.base_url_invalid')]);
        }

        DB::transaction(function () use ($organizationId, $apiKey, $baseUrl, $actorId, $reason): void {
            $provider = $this->ensureProvider();
            $before = $this->view($organizationId);

            $connection = IntegrationConnection::query()
                ->forOrganization($organizationId)
                ->where('provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            if ($connection === null) {
                $connection = new IntegrationConnection();
                $connection->organization_id = $organizationId;
                $connection->provider_id = (string) $provider->id;
                /*
                 * يبدأ Pending لا Active: حفظ المفتاح ليس تشغيلًا. التشغيل قرار
                 * منفصل بزر ظاهر وسبب مكتوب، حتى لا يبدأ البوت بالرد على طلاب
                 * لمجرد أن أحدًا أدخل مفتاحًا.
                 */
                $connection->status = ConnectionStatus::Pending;
            }

            $settings = $connection->settings ?? [];
            $settings['base_url'] = rtrim($baseUrl, '/');
            unset($settings['state']);

            $connection->settings = $settings;
            $connection->credentials = ['api_key' => trim($apiKey)];
            $connection->save();

            $this->audit->record($organizationId, $actorId, 'user',
                'support_bot.connection_saved', IntegrationConnection::class, (string) $connection->id,
                $before, $this->view($organizationId), $reason);
        });
    }

    public function verify(string $apiKey, string $baseUrl): ?string
    {
        if (!$this->validUrl($baseUrl)) {
            return 'base_url_invalid';
        }

        $model = config('llm.providers.anthropic.models.classify');
        $apiVersion = config('llm.providers.anthropic.api_version');
        $timeout = config('llm.providers.anthropic.connect_timeout_seconds');

        if (!is_string($model) || !is_string($apiVersion) || !is_int($timeout)) {
            return 'configuration_invalid';
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders(['x-api-key' => $apiKey, 'anthropic-version' => $apiVersion])
                ->timeout($timeout)
                ->post(rtrim($baseUrl, '/').'/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 1,
                    'messages' => [['role' => 'user', 'content' => '.']],
                ]);
        } catch (ConnectionException) {
            return 'network_error';
        }

        /*
         * 400 مقبول هنا: الطلب الأدنى قد يُرفض لشكله، لكنه لا يُرفض إلا بعد أن
         * يقبل المزوّد المفتاح. ما يهمنا هو 401/403 وحدهما.
         */
        if ($response->status() === 401 || $response->status() === 403) {
            return 'unauthorized';
        }

        return $response->serverError() ? 'provider_unavailable' : null;
    }

    public function setActive(string $organizationId, bool $active, string $actorId, string $reason): bool
    {
        return (bool) DB::transaction(function () use ($organizationId, $active, $actorId, $reason): bool {
            $provider = IntegrationProvider::query()->where('key', self::PROVIDER_KEY)->first();

            if ($provider === null) {
                return false;
            }

            $connection = IntegrationConnection::query()
                ->forOrganization($organizationId)
                ->where('provider_id', $provider->id)
                ->lockForUpdate()
                ->first();

            if ($connection === null) {
                return false;
            }

            $before = $this->view($organizationId);
            $target = $active ? ConnectionStatus::Active : ConnectionStatus::Disabled;

            if ($connection->status === $target) {
                return false;
            }

            if (!$connection->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'active' => __('support_bot::errors.toggle_blocked'),
                ]);
            }

            /*
             * التشغيل يحتاج مفتاحًا محفوظًا؛ الإيقاف لا يحتاج شيئًا. مفتاح
             * الطوارئ يجب أن يعمل حتى لو كان المزوّد نفسه معطلًا أو المفتاح
             * مسحوبًا — وهي بالضبط اللحظة التي يُراد فيها الإيقاف.
             */
            if ($active) {
                $apiKey = (string) (($connection->credentials ?? [])['api_key'] ?? '');

                if (trim($apiKey) === '') {
                    throw ValidationException::withMessages([
                        'active' => __('support_bot::errors.api_key_required'),
                    ]);
                }
            }

            $connection->status = $target;
            $connection->activated_at = $active ? now('UTC') : $connection->activated_at;
            $connection->disabled_at = $active ? null : now('UTC');
            $connection->save();

            $this->audit->record($organizationId, $actorId, 'user',
                'support_bot.connection_toggled', IntegrationConnection::class, (string) $connection->id,
                $before, $this->view($organizationId), $reason);

            return true;
        });
    }

    public function recordState(string $organizationId, string $state): void
    {
        if (!in_array($state, ['auth_failed', 'overloaded', 'healthy'], true)) {
            return;
        }

        DB::transaction(function () use ($organizationId, $state): void {
            $connection = $this->connection($organizationId);

            if ($connection === null) {
                return;
            }

            $settings = $connection->settings ?? [];

            if (($settings['state'] ?? null) === $state) {
                return;
            }

            $settings['state'] = $state;
            $connection->settings = $settings;
            $connection->save();
        });
    }

    private function ensureProvider(): IntegrationProvider
    {
        return IntegrationProvider::query()->updateOrCreate(
            ['key' => self::PROVIDER_KEY],
            [
                'name' => ['ar' => 'أنثروبيك', 'en' => 'Anthropic'],
                'category' => 'ai',
                'driver' => 'anthropic_messages',
                'is_active' => true,
            ],
        );
    }

    private function connection(string $organizationId): ?IntegrationConnection
    {
        $providerId = IntegrationProvider::query()->where('key', self::PROVIDER_KEY)->value('id');

        if (!is_string($providerId)) {
            return null;
        }

        return IntegrationConnection::query()
            ->forOrganization($organizationId)
            ->where('provider_id', $providerId)
            ->first();
    }

    private function validUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && str_starts_with($url, 'https://');
    }
}
