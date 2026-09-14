<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\Integrations\Infrastructure\Gateways\GreenApiGateway;

final readonly class GreenApiConnectionManager implements GreenApiConnections
{
    private const PROVIDER_KEY = 'green_api';

    public function __construct(private Factory $http, private AuditRecorder $audit) {}

    /** @return array<string, mixed> */
    public function view(string $organizationId): array
    {
        $connection = $this->connection($organizationId);
        $settings = $connection === null ? [] : ($connection->settings ?? []);
        $secrets = $connection === null ? [] : ($connection->credentials ?? []);
        $view = [
            'api_url' => (string) ($settings['api_url'] ?? config('notifications.channels.whatsapp.green_api.api_url', 'https://api.green-api.com')),
            'instance_id' => (string) ($settings['instance_id'] ?? ''),
            'configured' => is_string($secrets['token'] ?? null) && $secrets['token'] !== '',
            'enabled' => $connection?->status === ConnectionStatus::Active,
            'webhook_registered' => (bool) ($settings['webhook_registered'] ?? false),
            'state' => (string) ($settings['state'] ?? ''),
        ];
        $view['version'] = hash('sha256', json_encode([
            $connection?->id,
            $connection?->updated_at?->toISOString(),
            $view,
        ], JSON_THROW_ON_ERROR));

        return $view;
    }

    /** @return array{api_url: string, instance_id: string, token: string}|null */
    public function credentials(string $organizationId): ?array
    {
        $connection = $this->connection($organizationId);
        if ($connection !== null) {
            if ($connection->status !== ConnectionStatus::Active) {
                return null;
            }

            $settings = $connection->settings ?? [];
            $secrets = $connection->credentials ?? [];
        } else {
            if (!(bool) config('notifications.channels.whatsapp.enabled', false)) {
                return null;
            }

            $settings = (array) config('notifications.channels.whatsapp.green_api', []);
            $secrets = ['token' => $settings['token'] ?? null];
        }

        $apiUrl = $settings['api_url'] ?? null;
        $instanceId = $settings['instance_id'] ?? null;
        $token = $secrets['token'] ?? null;

        return is_string($apiUrl) && is_string($instanceId) && is_string($token)
            && $this->validUrl($apiUrl) && preg_match('/^\d+$/', $instanceId) === 1
            && preg_match('/^[A-Za-z0-9]+$/', $token) === 1
                ? ['api_url' => rtrim($apiUrl, '/'), 'instance_id' => $instanceId, 'token' => $token]
                : null;
    }

    public function isEnabled(string $organizationId): bool
    {
        return $this->credentials($organizationId) !== null;
    }

    public function isChannelEnabled(string $organizationId): bool
    {
        $globallyEnabled = (bool) config('notifications.channels.whatsapp.enabled', false);
        if (config('notifications.channels.whatsapp.gateway') !== GreenApiGateway::class) {
            return $globallyEnabled;
        }

        return $this->connection($organizationId) === null
            ? $globallyEnabled
            : $this->isEnabled($organizationId);
    }

    public function verify(string $apiUrl, string $instanceId, string $token): ?string
    {
        if (!$this->validUrl($apiUrl)
            || preg_match('/^\d+$/', $instanceId) !== 1
            || preg_match('/^[A-Za-z0-9]+$/', $token) !== 1) {
            return null;
        }

        try {
            $response = $this->http->acceptJson()
                ->timeout((int) config('notifications.channels.whatsapp.green_api.timeout_seconds', 15))
                ->withoutRedirecting()
                ->get(rtrim($apiUrl, '/').'/waInstance'.$instanceId.'/getStateInstance/'.$token);
        } catch (ConnectionException) {
            return null;
        }

        $state = $response->successful() ? $response->json('stateInstance') : null;

        return is_string($state) ? $state : null;
    }

    /** @param array<string, mixed> $data */
    public function save(string $organizationId, array $data, string $actorId, string $reason): void
    {
        $before = $this->view($organizationId);
        if (!hash_equals((string) $before['version'], (string) $data['version'])) {
            throw ValidationException::withMessages(['version' => __('console.settings.concurrent')]);
        }

        $existing = $this->connection($organizationId);
        $existingSecrets = $existing === null ? [] : ($existing->credentials ?? []);
        $token = (string) (($data['token'] ?? '') ?: ($existingSecrets['token'] ?? ''));
        if ($data['enabled']) {
            if ($token === '') {
                throw ValidationException::withMessages(['token' => __('console_settings.green_api.token_required')]);
            }

            $state = $this->verify((string) $data['api_url'], (string) $data['instance_id'], $token);
            if ($state !== 'authorized') {
                throw ValidationException::withMessages(['token' => __('console_settings.green_api.connection_failed')]);
            }
            $data['state'] = $state;
        } else {
            $data['state'] = '';
        }

        DB::transaction(function () use ($organizationId, $data, $actorId, $reason, $before): void {
            $provider = IntegrationProvider::query()->firstOrCreate(
                ['key' => self::PROVIDER_KEY],
                [
                    'name' => ['ar' => 'Green API', 'en' => 'Green API'],
                    'category' => 'messaging',
                    'driver' => 'green_api',
                    'is_active' => true,
                ],
            );
            $connection = IntegrationConnection::query()
                ->forOrganization($organizationId)
                ->where('provider_id', $provider->id)
                ->lockForUpdate()
                ->first();
            $oldSettings = $connection === null ? [] : ($connection->settings ?? []);
            $oldSecrets = $connection === null ? [] : ($connection->credentials ?? []);
            $target = $data['enabled'] ? ConnectionStatus::Active : ConnectionStatus::Disabled;
            $settings = [
                ...$oldSettings,
                'api_url' => rtrim((string) $data['api_url'], '/'),
                'instance_id' => (string) $data['instance_id'],
                'state' => (string) $data['state'],
            ];
            if ($settings['api_url'] !== ($oldSettings['api_url'] ?? null)
                || $settings['instance_id'] !== ($oldSettings['instance_id'] ?? null)
                || (($data['token'] ?? '') !== '' && $data['token'] !== ($oldSecrets['token'] ?? null))) {
                $settings['webhook_registered'] = false;
            }

            if ($connection === null) {
                $connection = new IntegrationConnection([
                    'organization_id' => $organizationId,
                    'provider_id' => $provider->id,
                    'status' => ConnectionStatus::Pending,
                ]);
            }
            if ($connection->status !== $target && !$connection->status->canTransitionTo($target)) {
                throw new \LogicException('Green API connection cannot transition to requested status.');
            }

            $connection->status = $target;
            $connection->settings = $settings;
            $connection->credentials = [
                'token' => (string) (($data['token'] ?? '') ?: ($oldSecrets['token'] ?? '')),
                'webhook_token' => (string) ($oldSecrets['webhook_token'] ?? Str::random(48)),
            ];
            $connection->activated_at = $target === ConnectionStatus::Active ? now('UTC') : $connection->activated_at;
            $connection->disabled_at = $target === ConnectionStatus::Disabled ? now('UTC') : null;
            $connection->save();
            $after = $this->view($organizationId);
            if ($before !== $after) {
                $this->audit->record($organizationId, $actorId, 'user',
                    'console.settings.green_api', IntegrationConnection::class, (string) $connection->id,
                    $before, $after, $reason);
            }
        });
    }

    public function organizationForWebhook(string $instanceId, string $authorization): ?string
    {
        if (preg_match('/^\d+$/', $instanceId) !== 1 || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $providerId = IntegrationProvider::query()->where('key', self::PROVIDER_KEY)->value('id');
        if (!is_string($providerId)) {
            return null;
        }

        $connections = IntegrationConnection::query()
            ->where('provider_id', $providerId)
            ->where('status', ConnectionStatus::Active)
            ->where('settings->instance_id', $instanceId)
            ->get();

        foreach ($connections as $connection) {
            $secrets = $connection->credentials ?? [];
            $expected = $secrets['webhook_token'] ?? null;
            if (is_string($expected) && hash_equals($expected, substr($authorization, 7))) {
                return (string) $connection->organization_id;
            }
        }

        return null;
    }

    public function registerWebhook(string $organizationId, string $actorId, string $reason): bool
    {
        $connection = $this->connection($organizationId);
        $credentials = $this->credentials($organizationId);
        if ($connection === null || $credentials === null) {
            return false;
        }

        $secrets = $connection->credentials ?? [];
        $webhookToken = $secrets['webhook_token'] ?? null;
        $appUrl = rtrim((string) config('app.url'), '/');
        if (!is_string($webhookToken) || !str_starts_with($appUrl, 'https://')) {
            return false;
        }

        try {
            $response = $this->http->acceptJson()->asJson()
                ->timeout((int) config('notifications.channels.whatsapp.green_api.timeout_seconds', 15))
                ->withoutRedirecting()
                ->post($credentials['api_url'].'/waInstance'.$credentials['instance_id'].'/setSettings/'.$credentials['token'], [
                    'webhookUrl' => $appUrl.'/api/webhooks/green-api',
                    'webhookUrlToken' => 'Bearer '.$webhookToken,
                    'incomingWebhook' => 'yes',
                    'outgoingWebhook' => 'yes',
                    'outgoingAPIMessageWebhook' => 'yes',
                    'stateWebhook' => 'yes',
                ]);
        } catch (ConnectionException) {
            return false;
        }

        if (!$response->successful() || $response->json('saveSettings') !== true) {
            return false;
        }

        DB::transaction(function () use ($connection, $organizationId, $actorId, $reason): void {
            $before = $this->view($organizationId);
            $settings = $connection->settings ?? [];
            $settings['webhook_registered'] = true;
            $connection->settings = $settings;
            $connection->save();
            $this->audit->record($organizationId, $actorId, 'user',
                'console.settings.green_api.webhook', IntegrationConnection::class, (string) $connection->id,
                $before, $this->view($organizationId), $reason);
        });

        return true;
    }

    public function recordState(string $organizationId, string $state): void
    {
        if (!in_array($state, [
            'authorized', 'notAuthorized', 'blocked', 'sleepMode', 'starting', 'suspended', 'yellowCard',
        ], true)) {
            return;
        }

        DB::transaction(function () use ($organizationId, $state): void {
            $connection = $this->connection($organizationId);
            if ($connection === null || $connection->status !== ConnectionStatus::Active) {
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

    private function validUrl(string $apiUrl): bool
    {
        return preg_match('#^https://(?:api\.green-api\.com|(?:\d+\.)?api\.greenapi\.com)/?$#', $apiUrl) === 1;
    }
}
