<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Events;

use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Shared\Domain\DomainEvent;

final class GuardianLinkedToStudent extends DomainEvent
{
    public function __construct(
        public readonly string $guardianLinkId,
        public readonly string $guardianProfileId,
        public readonly string $studentProfileId,
        public readonly GuardianRelationship $relationship,
        public readonly bool $isPrimary,
        public readonly bool $canActFor,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'guardians.linked_to_student';
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
            'relationship' => $this->relationship->value,
            'is_primary' => $this->isPrimary,
            'can_act_for' => $this->canActFor,
        ];
    }
}
