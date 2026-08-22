<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

final class PostponementRejected extends DomainEvent
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $sessionId,
        public readonly ?string $reason,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.postponement_rejected';
    }

    public function module(): string
    {
        return 'Scheduling';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'request_id' => $this->requestId,
            'session_id' => $this->sessionId,
            'reason' => $this->reason,
        ];
    }
}
