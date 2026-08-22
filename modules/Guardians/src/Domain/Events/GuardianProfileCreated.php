<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Events;

use Shared\Domain\DomainEvent;

final class GuardianProfileCreated extends DomainEvent
{
    public function __construct(
        public readonly string $guardianProfileId,
        public readonly string $organizationId,
        public readonly string $userId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'guardians.profile_created';
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
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
        ];
    }
}
