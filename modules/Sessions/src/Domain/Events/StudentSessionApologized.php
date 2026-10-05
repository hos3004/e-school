<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

final class StudentSessionApologized extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $sessionParticipantId,
        public readonly string $studentProfileId,
        public readonly string $studentUserId,
        public readonly ?string $teacherUserId,
        public readonly bool $groupSession,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.student_apologized';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'session_participant_id' => $this->sessionParticipantId,
            'student_profile_id' => $this->studentProfileId,
            'student_user_id' => $this->studentUserId,
            'teacher_user_id' => $this->teacherUserId,
            'group_session' => $this->groupSession,
            'reason' => $this->reason,
        ];
    }
}
