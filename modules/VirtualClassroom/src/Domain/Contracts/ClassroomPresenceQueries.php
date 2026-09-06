<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Contracts;

use Carbon\CarbonImmutable;

interface ClassroomPresenceQueries
{
    public function wasUserPresent(
        string $sessionId,
        string $userId,
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
    ): bool;
}
