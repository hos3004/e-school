<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Events;

use Shared\Domain\DomainEvent;

final class ProgramCreated extends DomainEvent
{
    /**
     * @param array<string, string> $name
     */
    public function __construct(
        public readonly string $programId,
        public readonly string $organizationId,
        public readonly string $code,
        public readonly array $name,
        public readonly ?int $durationWeeks,
        public readonly int $defaultSessionMinutes,
        public readonly ?int $defaultRate,
        public readonly string $currency,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academics.program_created';
    }

    public function module(): string
    {
        return 'Academics';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'program_id' => $this->programId,
            'organization_id' => $this->organizationId,
            'code' => $this->code,
            'name' => $this->name,
            'duration_weeks' => $this->durationWeeks,
            'default_session_minutes' => $this->defaultSessionMinutes,
            'default_rate' => $this->defaultRate,
            'currency' => $this->currency,
        ];
    }
}
