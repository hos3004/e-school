<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\ValueObjects;

final readonly class SessionParticipantAdministrationData
{
    /** @param array<string, string> $sessionTitle */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $sessionId,
        public string $studentProfileId,
        public string $enrollmentId,
        public string $courseId,
        public ?string $groupId,
        public string $staffProfileId,
        public array $sessionTitle,
        public string $sessionStatus,
        public string $scheduledStart,
        public string $scheduledEnd,
        public ?string $excusedAt,
        public ?string $firstJoinedAt,
        public ?string $lastLeftAt,
        public int $attendedMinutes,
        public bool $invitationActive,
    ) {}
}
