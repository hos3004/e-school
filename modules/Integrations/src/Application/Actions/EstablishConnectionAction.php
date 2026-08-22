<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Events\ConnectionEstablished;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء اتصال جديد بين مؤسسة ومزوّد خارجي — يبدأ في حالة Pending.
 *
 * حراس: المزوّد موجود ومُفعَّل، ولم تبلغ المؤسسة سقف الاتصالات على المزوّد.
 */
final readonly class EstablishConnectionAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $settings
     */
    public function execute(
        string $organizationId,
        string $providerId,
        array $credentials = [],
        array $settings = [],
    ): IntegrationConnection {
        $provider = IntegrationProvider::query()->find($providerId);

        if ($provider === null) {
            throw BusinessRuleViolation::make(
                'integrations.provider_not_found',
                'integrations::errors.provider_not_found',
                ['provider_id' => $providerId],
            );
        }

        if (!$provider->is_active) {
            throw BusinessRuleViolation::make(
                'integrations.provider_inactive',
                'integrations::errors.provider_inactive',
                ['key' => (string) $provider->key],
            );
        }

        $maxPerProvider = (int) config('integrations.connections.max_per_provider');

        $existing = IntegrationConnection::query()
            ->forOrganization($organizationId)
            ->where('provider_id', $providerId)
            ->count();

        if ($existing >= $maxPerProvider) {
            throw BusinessRuleViolation::make(
                'integrations.connection_limit_reached',
                'integrations::errors.connection_limit_reached',
                ['max' => $maxPerProvider],
            );
        }

        $connection = $this->transaction->run(function () use ($organizationId, $provider, $credentials, $settings): IntegrationConnection {
            return IntegrationConnection::query()->create([
                'organization_id' => $organizationId,
                'provider_id' => (string) $provider->getKey(),
                'status' => ConnectionStatus::Pending,
                'credentials' => $credentials === [] ? null : $credentials,
                'settings' => $settings === [] ? null : $settings,
            ]);
        });

        $this->events->dispatch(new ConnectionEstablished(
            connectionId: (string) $connection->getKey(),
            organizationId: $organizationId,
            providerId: (string) $provider->getKey(),
        ));

        return $connection;
    }
}
