<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomPresenceQueries;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\Models\ClassroomEvent;

final readonly class ClassroomPresenceQueryService implements ClassroomPresenceQueries
{
    public function wasUserPresent(
        string $sessionId,
        string $userId,
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
    ): bool {
        $classroomIds = Classroom::query()
            ->forSession($sessionId)
            ->pluck('id');

        if ($classroomIds->isEmpty()) {
            return false;
        }

        $events = ClassroomEvent::query()
            ->whereIn('classroom_id', $classroomIds)
            ->where('user_id', $userId)
            ->whereIn('event_type', [
                ClassroomEventType::ParticipantJoined->value,
                ClassroomEventType::ParticipantLeft->value,
            ])
            ->where('occurred_at', '<=', $scheduledEnd)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $joinedAt = null;

        foreach ($events as $event) {
            $occurredAt = CarbonImmutable::parse($event->occurred_at, 'UTC');

            if ($event->event_type === ClassroomEventType::ParticipantJoined) {
                $joinedAt ??= $occurredAt;

                continue;
            }

            if (
                $joinedAt instanceof CarbonImmutable
                && $this->overlapsOfficialInterval(
                    $joinedAt,
                    $occurredAt,
                    $scheduledStart,
                    $scheduledEnd,
                )
            ) {
                return true;
            }

            $joinedAt = null;
        }

        return $joinedAt instanceof CarbonImmutable
            && $this->overlapsOfficialInterval($joinedAt, $scheduledEnd, $scheduledStart, $scheduledEnd);
    }

    private function overlapsOfficialInterval(
        CarbonImmutable $joinedAt,
        CarbonImmutable $leftAt,
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
    ): bool {
        return $joinedAt->lessThan($scheduledEnd)
            && $leftAt->greaterThan($scheduledStart);
    }
}
