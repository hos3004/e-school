<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * تمت إتاحة المعلم واعتمدت للفترات المحددة.
 */
final class TeacherAvailabilityApproved extends DomainEvent
{
    public function __construct(
        public readonly string $staffProfileId,
        public readonly string $teacherUserId,
        public readonly string $organizationId,
        public readonly string $availabilityId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'teacher.availability.approved';
    }

    public function module(): string
    {
        return 'Staff';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'staff_profile_id' => $this->staffProfileId,
            'teacher_user_id' => $this->teacherUserId,
            'organization_id' => $this->organizationId,
            'availability_id' => $this->availabilityId,
        ];
    }
}
