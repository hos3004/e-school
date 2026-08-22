<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

use Modules\Sessions\Domain\Enums\SessionStatus;

/**
 * أُلغيت الحصة — من طرف الطالب أو المعلم أو الإدارة.
 */
final class SessionCancelled extends SessionEvent
{
    public function __construct(
        string $sessionId,
        string $organizationId,
        string $courseId,
        string $staffProfileId,
        public readonly SessionStatus $cancelledAs,
        public readonly string $cancelledAt,
        public readonly ?string $cancelledById,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($sessionId, $organizationId, $courseId, $staffProfileId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'sessions.cancelled';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
            'cancelled_as' => $this->cancelledAs->value,
            'cancelled_at' => $this->cancelledAt,
            'cancelled_by_id' => $this->cancelledById,
            'reason' => $this->reason,
        ];
    }
}
