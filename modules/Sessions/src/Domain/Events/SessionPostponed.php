<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * أُجّلت الحصة وأُنشئت حصة تلافي مرتبطة بها.
 */
final class SessionPostponed extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly string $makeupSessionId,
        public readonly string $makeupStart,
        public readonly string $makeupEnd,
        public readonly ?string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.postponed';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'makeup_session_id' => $this->makeupSessionId,
            'makeup_start' => $this->makeupStart,
            'makeup_end' => $this->makeupEnd,
            'reason' => $this->reason,
        ];
    }
}
