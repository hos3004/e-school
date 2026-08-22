<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أساس أحداث الاتصالات — يثبّت المعرّفات المشتركة.
 */
abstract class ConnectionEvent extends DomainEvent
{
    public function __construct(
        public readonly string $connectionId,
        public readonly string $organizationId,
        public readonly string $providerId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'integrations';
    }
}
