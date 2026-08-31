<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\ValueObjects;

final readonly class SessionAdministrationData
{
    /** @param array<string, string> $title */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $groupId,
        public string $courseId,
        public string $staffProfileId,
        public string $status,
        public array $title,
        public string $scheduledStart,
        public string $scheduledEnd,
        public ?int $attendedMinutes = null,
        public ?string $originalStaffProfileId = null,
        public ?string $sessionType = null,
        public ?string $actualStart = null,
        public ?string $actualEnd = null,
        public ?string $cancellationReason = null,
        public ?string $finalizedAt = null,
    ) {}
}
