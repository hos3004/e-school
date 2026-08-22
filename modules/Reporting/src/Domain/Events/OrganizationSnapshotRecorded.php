<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * سُجِّلت لقطة تنظيمية لليوم — تُستخدم لتتبّع دورة بناء اللقطات.
 */
final class OrganizationSnapshotRecorded extends DomainEvent
{
    public function __construct(
        public readonly string $snapshotId,
        public readonly string $organizationId,
        public readonly string $snapshotDate,
        public readonly int $sessionsHeld,
        public readonly int $attendanceRateBp,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'reporting.organization_snapshot_recorded';
    }

    public function module(): string
    {
        return 'reporting';
    }

    public function payload(): array
    {
        return [
            'snapshot_id' => $this->snapshotId,
            'organization_id' => $this->organizationId,
            'snapshot_date' => $this->snapshotDate,
            'sessions_held' => $this->sessionsHeld,
            'attendance_rate_bp' => $this->attendanceRateBp,
        ];
    }
}
