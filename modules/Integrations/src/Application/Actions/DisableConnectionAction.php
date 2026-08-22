<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Integrations\Application\Concerns\TransitionsConnectionStatus;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Events\ConnectionDisabled;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Shared\Support\Transaction;

/**
 * إيقاف اتصال بإدارة المؤسسة — مع سبب موثّق للتدقيق.
 */
final readonly class DisableConnectionAction
{
    use TransitionsConnectionStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(IntegrationConnection $connection, ?string $reason = null, ?string $actorId = null): IntegrationConnection
    {
        $this->assertCanTransition($connection, ConnectionStatus::Disabled);

        $disabledAt = CarbonImmutable::now('UTC');

        $this->transaction->run(function () use ($connection, $disabledAt): void {
            $connection->update([
                'status' => ConnectionStatus::Disabled,
                'disabled_at' => $disabledAt,
            ]);
        });

        $this->events->dispatch(new ConnectionDisabled(
            connectionId: (string) $connection->getKey(),
            organizationId: (string) $connection->organization_id,
            providerId: (string) $connection->provider_id,
            disabledAt: $disabledAt,
            reason: $reason,
            actorId: $actorId,
        ));

        return $connection->refresh();
    }
}
