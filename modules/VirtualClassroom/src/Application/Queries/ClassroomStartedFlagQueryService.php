<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Queries;

use Modules\VirtualClassroom\Domain\Contracts\ClassroomStartedFlagQuery;
use Modules\VirtualClassroom\Domain\Models\Classroom;

final readonly class ClassroomStartedFlagQueryService implements ClassroomStartedFlagQuery
{
    public function forSessionIds(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $started = Classroom::query()
            ->whereIn('session_id', $sessionIds)
            ->whereNotNull('started_at')
            ->pluck('session_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $flags = array_fill_keys($sessionIds, false);

        foreach ($started as $sessionId) {
            $flags[$sessionId] = true;
        }

        return $flags;
    }
}
