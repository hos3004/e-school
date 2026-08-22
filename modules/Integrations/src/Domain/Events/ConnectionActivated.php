<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

use Carbon\CarbonImmutable;

final class ConnectionActivated extends ConnectionEvent
{
    public function __construct(
        string $connectionId,
        string $organizationId,
        string $providerId,
        public readonly CarbonImmutable $activatedAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($connectionId, $organizationId, $providerId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'integrations.connection_activated';
    }

    public function payload(): array
    {
        return [
            'connection_id' => $this->connectionId,
            'organization_id' => $this->organizationId,
            'provider_id' => $this->providerId,
            'activated_at' => $this->activatedAt->toIso8601String(),
        ];
    }
}
