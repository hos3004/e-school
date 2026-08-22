<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Shared\Domain\DomainEvent;

final class StudentProfileUpdated extends DomainEvent
{
    /**
     * @param  array<string, mixed>  $changes  أسماء الأعمدة والقيم الجديدة فقط — قيَم بدائية
     */
    public function __construct(
        public readonly string $studentId,
        public readonly string $organizationId,
        public readonly array $changes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'students.profile_updated';
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
            'changes' => $this->changes,
        ];
    }
}
