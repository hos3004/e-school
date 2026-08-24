<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Events;

use Shared\Domain\DomainEvent;

final class StudentAssignedToTeacher extends DomainEvent
{
    public function __construct(
        public readonly string $studentProfileId,
        public readonly string $studentUserId,
        public readonly string $teacherProfileId,
        public readonly string $teacherUserId,
        public readonly string $organizationId,
        public readonly string $programId,
        public readonly ?string $courseId = null,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'student.assigned_to_teacher';
    }

    public function module(): string
    {
        return 'Students';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'student_profile_id' => $this->studentProfileId,
            'student_user_id' => $this->studentUserId,
            'teacher_profile_id' => $this->teacherProfileId,
            'teacher_user_id' => $this->teacherUserId,
            'organization_id' => $this->organizationId,
            'program_id' => $this->programId,
            'course_id' => $this->courseId,
        ];
    }
}
