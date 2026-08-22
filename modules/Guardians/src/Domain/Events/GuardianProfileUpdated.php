<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Events;

use Shared\Domain\DomainEvent;

final class GuardianProfileUpdated extends DomainEvent
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly string $guardianProfileId,
        public readonly array $changes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'guardians.profile_updated';
    }

    public function module(): string
    {
        return 'Guardians';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'guardian_profile_id' => $this->guardianProfileId,
            'changes' => $this->changes,
        ];
    }
}
