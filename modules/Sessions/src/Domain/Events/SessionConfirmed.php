<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

/**
 * أكّد الطرفان الحضور للحصة.
 */
final class SessionConfirmed extends SessionEvent
{
    public function name(): string
    {
        return 'sessions.confirmed';
    }

    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'organization_id' => $this->organizationId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $this->staffProfileId,
        ];
    }
}
