<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/** Upcoming-session reminder for students and the teacher. */
final class SessionApproaching extends SessionEvent
{
    /**
     * @param list<string> $studentUserIds
     * @param array<string, string> $courseName
     */
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $scheduledStart,
        public readonly string $scheduledEnd,
        public readonly array $studentUserIds,
        public readonly ?string $teacherUserId,
        public readonly array $courseName,
        public readonly int $durationMinutes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.approaching';
    }

    public function payLoad(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'scheduled_start' => $this->scheduledStart,
            'scheduled_end' => $this->scheduledEnd,
            'student_user_ids' => $this->studentUserIds,
            'teacher_user_id' => $this->teacherUserId,
            'course_name' => $this->courseName,
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
