<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * حُدِّثت لوحة المعلم بعد إسقاط حدث خارجي عليها.
 */
final class TeacherDashboardUpdated extends DomainEvent
{
    public function __construct(
        public readonly string $dashboardId,
        public readonly string $organizationId,
        public readonly string $staffProfileId,
        public readonly int $sessionsCompleted,
        public readonly int $payoutMinor,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'reporting.teacher_dashboard_updated';
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
            'staff_profile_id' => $this->staffProfileId,
            'sessions_completed' => $this->sessionsCompleted,
            'payout_minor' => $this->payoutMinor,
        ];
    }
}
