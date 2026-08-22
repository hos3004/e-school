<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * أُنشئت حصة وجُدولت وأُعلنت للطرفين.
 */
final class SessionScheduled extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $scheduledStart,
        public readonly string $scheduledEnd,
        public readonly ?string $groupId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.scheduled';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'group_id' => $this->groupId,
            'scheduled_start' => $this->scheduledStart,
            'scheduled_end' => $this->scheduledEnd,
        ];
    }
}
