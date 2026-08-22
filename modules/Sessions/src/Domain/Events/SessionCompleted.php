<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * اعتُمدت الحصة وقُفلت نهائيًا — تُنشأ بعدها قيود المستحقات.
 */
final class SessionCompleted extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly int $attendedMinutes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.completed';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'attended_minutes' => $this->attendedMinutes,
        ];
    }
}
