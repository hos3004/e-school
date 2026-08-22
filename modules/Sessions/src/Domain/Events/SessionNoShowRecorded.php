<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * رُصد غياب الطالب بدون إشعار ضمن المهلة — مخالفة على الطالب.
 */
final class SessionNoShowRecorded extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly ?string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.no_show_recorded';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'reason' => $this->reason,
        ];
    }
}
