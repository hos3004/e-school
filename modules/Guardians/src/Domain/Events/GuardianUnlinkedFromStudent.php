<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Events;

use Shared\Domain\DomainEvent;

final class GuardianUnlinkedFromStudent extends DomainEvent
{
    public function __construct(
        public readonly string $guardianLinkId,
        public readonly string $guardianProfileId,
        public readonly string $studentProfileId,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'guardians.unlinked_from_student';
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
            'reason' => $this->reason,
        ];
    }
}
