<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\ValueObjects;

final readonly class TeacherAvailabilityData
{
    public function __construct(
        public string $id,
        public int $weekday,
        public string $startTime,
        public string $endTime,
        public string $timezone,
        public string $approvalStatus,
        public ?string $decisionReason,
        public string $effectiveFrom,
        public ?string $effectiveTo,
    ) {}
}
