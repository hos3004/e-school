<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\ValueObjects\ScheduledParticipantData;

interface SessionSchedulingGateway
{
    /**
     * @param array<string, string> $title
     * @param list<ScheduledParticipantData> $participants
     */
    public function createScheduledSession(
        string $organizationId,
        string $scheduleId,
        ?string $groupId,
        string $courseId,
        string $staffProfileId,
        string $sessionType,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $title,
        array $participants,
        ?string $actorId,
    ): string;

    public function scheduleMakeup(
        string $organizationId,
        string $originalSessionId,
        CarbonImmutable $startsAt,
        string $actorId,
        string $reason,
    ): string;

    public function supersedeFutureForSchedule(
        string $organizationId,
        string $scheduleId,
        CarbonImmutable $from,
        string $actorId,
        string $reason,
    ): int;

    public function addParticipantToFutureGroupSessions(
        string $organizationId,
        string $groupId,
        ?string $courseId,
        string $studentProfileId,
        string $enrollmentId,
    ): int;

    public function revokeParticipantFromFutureGroupSessions(
        string $organizationId,
        string $groupId,
        string $studentProfileId,
        ?string $actorId,
        string $reason,
    ): int;
}
