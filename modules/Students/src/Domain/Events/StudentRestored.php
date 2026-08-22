<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Shared\Domain\DomainEvent;

final class StudentRestored extends DomainEvent
{
    public function __construct(
        public readonly string $studentId,
        public readonly string $organizationId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'students.restored';
    }

    public function module(): string
    {
        return 'Students';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'student_id' => $this->studentId,
            'organization_id' => $this->organizationId,
        ];
    }
}
