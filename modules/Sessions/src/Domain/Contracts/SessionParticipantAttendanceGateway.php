<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Carbon\CarbonImmutable;

interface SessionParticipantAttendanceGateway
{
    public function recordProviderEvent(
        string $sessionId,
        string $userId,
        string $type,
        CarbonImmutable $occurredAt,
    ): void;

    public function closeOpenIntervals(
        string $sessionId,
        CarbonImmutable $endedAt,
    ): void;
}
