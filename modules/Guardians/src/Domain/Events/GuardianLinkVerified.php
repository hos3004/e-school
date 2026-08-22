<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Events;

use Shared\Domain\DomainEvent;

final class GuardianLinkVerified extends DomainEvent
{
    public function __construct(
        public readonly string $guardianLinkId,
        public readonly string $guardianProfileId,
        public readonly string $studentProfileId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'guardians.link_verified';
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
            'guardian_link_id' => $this->guardianLinkId,
            'guardian_profile_id' => $this->guardianProfileId,
            'student_profile_id' => $this->studentProfileId,
        ];
    }
}
