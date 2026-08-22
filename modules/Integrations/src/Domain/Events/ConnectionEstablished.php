<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

final class ConnectionEstablished extends ConnectionEvent
{
    public function name(): string
    {
        return 'integrations.connection_established';
    }

    public function payload(): array
    {
        return [
            'connection_id' => $this->connectionId,
            'organization_id' => $this->organizationId,
            'provider_id' => $this->providerId,
        ];
    }
}
