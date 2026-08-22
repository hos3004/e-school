<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Integrations\Application\Concerns\TransitionsConnectionStatus;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Events\ConnectionActivated;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تفعيل اتصال — لا يُقبل التفعيل إلا إذا كان المزوّد نفسه مُفعَّلًا.
 */
final readonly class ActivateConnectionAction
{
    use TransitionsConnectionStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(IntegrationConnection $connection, ?string $actorId = null): IntegrationConnection
    {
        $this->assertCanTransition($connection, ConnectionStatus::Active);

        $provider = IntegrationProvider::query()->find($connection->provider_id);

        if ($provider === null || !$provider->is_active) {
            throw BusinessRuleViolation::make(
                'integrations.provider_inactive',
                'integrations::errors.provider_inactive',
                ['key' => (string) ($provider->key ?? $connection->provider_id)],
            );
        }

        $activatedAt = CarbonImmutable::now('UTC');

        $this->transaction->run(function () use ($connection, $activatedAt): void {
            $connection->update([
                'status' => ConnectionStatus::Active,
                'activated_at' => $activatedAt,
                'last_error_at' => null,
                'last_error_message' => null,
            ]);
        });

        $this->events->dispatch(new ConnectionActivated(
            connectionId: (string) $connection->getKey(),
            organizationId: (string) $connection->organization_id,
            providerId: (string) $connection->provider_id,
            activatedAt: $activatedAt,
            actorId: $actorId,
        ));

        return $connection->refresh();
    }
}
