<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

final class PostponementAlternativeProposed extends DomainEvent
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $sessionId,
        public readonly string $teacherProposedStart,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.postponement_alternative_proposed';
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
            'teacher_proposed_start' => $this->teacherProposedStart,
        ];
    }
}
