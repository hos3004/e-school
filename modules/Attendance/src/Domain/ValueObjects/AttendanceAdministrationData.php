<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\ValueObjects;

final readonly class AttendanceAdministrationData
{
    public function __construct(
        public string $id,
        public string $sessionParticipantId,
        public string $status,
        public string $derivedStatus,
        public int $attendedMinutes,
        public int $joinedAfterMinutes,
        public int $leftBeforeMinutes,
        public ?string $confirmedBy,
        public ?string $confirmedAt,
        public ?string $overrideReason,
    ) {}
}
