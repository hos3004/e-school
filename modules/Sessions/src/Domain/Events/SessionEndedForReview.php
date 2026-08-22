<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * انتهت الحصة وتنتظر رصد الحضور واعتماد المعلم.
 */
final class SessionEndedForReview extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $actualEnd,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.ended_for_review';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'actual_end' => $this->actualEnd,
        ];
    }
}
