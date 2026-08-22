<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * رُصد حضور أحد المشاركين (دخول أو خروج) واحتُسبت دقائق الحضور.
 */
final class SessionAttendanceRecorded extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $participantId,
        public readonly string $studentProfileId,
        public readonly ?string $firstJoinedAt,
        public readonly ?string $lastLeftAt,
        public readonly int $attendedMinutes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.attendance_recorded';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'participant_id' => $this->participantId,
            'student_profile_id' => $this->studentProfileId,
            'first_joined_at' => $this->firstJoinedAt,
            'last_left_at' => $this->lastLeftAt,
            'attended_minutes' => $this->attendedMinutes,
        ];
    }
}
