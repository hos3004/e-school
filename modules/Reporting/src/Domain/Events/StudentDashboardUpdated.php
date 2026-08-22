<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * حُدِّثت لوحة الطالب بعد إسقاط حدث خارجي عليها.
 */
final class StudentDashboardUpdated extends DomainEvent
{
    public function __construct(
        public readonly string $dashboardId,
        public readonly string $organizationId,
        public readonly string $enrollmentId,
        public readonly string $studentProfileId,
        public readonly int $sessionsAttended,
        public readonly int $sessionsMissed,
        public readonly int $attendanceRateBp,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'reporting.student_dashboard_updated';
    }

    public function module(): string
    {
        return 'reporting';
    }

    public function payload(): array
    {
        return [
            'dashboard_id' => $this->dashboardId,
            'organization_id' => $this->organizationId,
            'enrollment_id' => $this->enrollmentId,
            'student_profile_id' => $this->studentProfileId,
            'sessions_attended' => $this->sessionsAttended,
            'sessions_missed' => $this->sessionsMissed,
            'attendance_rate_bp' => $this->attendanceRateBp,
        ];
    }
}
