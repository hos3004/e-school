<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Application\Queries;

use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\Models\ClassroomEvent;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomAdministrationData;
use Modules\VirtualClassroom\Domain\ValueObjects\PersistentRoomData;
use Modules\VirtualClassroom\Domain\ValueObjects\RoomIdentity;

final readonly class ClassroomAdministrationQueryService implements ClassroomAdministrationQueries
{
    public function __construct(private SessionAdministrationQueries $sessions) {}

    public function findForSession(
        string $organizationId,
        string $sessionId,
    ): ?ClassroomAdministrationData {
        if ($this->sessions->findForOrganization($organizationId, $sessionId) === null) {
            return null;
        }

        /** @var Classroom|null $classroom */
        $classroom = Classroom::query()->forSession($sessionId)->first();

        return $classroom === null ? null : $this->data($classroom);
    }

    public function summaryForOrganization(string $organizationId): array
    {
        $sessionIds = $this->sessions->sessionIdsForOrganization($organizationId);
        $query = Classroom::query()->whereIn('session_id', $sessionIds);

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', ClassroomStatus::Pending)->count(),
            'provisioned' => (clone $query)->where('status', ClassroomStatus::Provisioned)->count(),
            'running' => (clone $query)->where('status', ClassroomStatus::Running)->count(),
            'ended' => (clone $query)->where('status', ClassroomStatus::Ended)->count(),
            'failed' => (clone $query)->where('status', ClassroomStatus::Failed)->count(),
        ];
    }

    public function generationForRoomIdentity(string $roomIdentity): int
    {
        $generation = Classroom::query()->forRoomIdentity($roomIdentity)->value('link_generation');

        return $generation === null ? 1 : (int) $generation;
    }

    public function persistentRoomsForOrganization(string $organizationId): array
    {
        return Classroom::query()
            ->whereNotNull('room_identity')
            ->orderBy('room_identity')
            ->get()
            ->map(function (Classroom $classroom) use ($organizationId): ?PersistentRoomData {
                $scheduleId = RoomIdentity::scheduleIdFrom((string) $classroom->room_identity);

                if ($scheduleId === null) {
                    return null;
                }

                $recent = $this->sessions->forSchedule($organizationId, $scheduleId, 1);

                // الجدول لا ينتمي لهذه المؤسسة، أو لم يُنتج حصصًا بعد.
                if ($recent === []) {
                    return null;
                }

                $titleByLocale = $recent[0]->title;
                $title = $titleByLocale[app()->getLocale()] ?? (reset($titleByLocale) ?: $scheduleId);

                return new PersistentRoomData(
                    scheduleId: $scheduleId,
                    label: (string) $title,
                    status: $classroom->status->value,
                    generation: (int) $classroom->link_generation,
                    rotatedAt: $classroom->rotated_at?->toIso8601String(),
                    lastErrorAt: $classroom->last_error_at?->toIso8601String(),
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    private function data(Classroom $classroom): ClassroomAdministrationData
    {
        $events = $classroom->events()
            ->latest('occurred_at')
            ->limit((int) config('virtual-classroom.admin_hub.max_events', 25))
            ->get()
            ->map(static fn (ClassroomEvent $event): array => [
                'id' => (string) $event->getKey(),
                'type' => $event->event_type->value,
                'external_user_id' => $event->external_user_id,
                'user_id' => $event->user_id,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values()->all();

        return new ClassroomAdministrationData(
            id: (string) $classroom->getKey(),
            sessionId: (string) $classroom->session_id,
            provider: (string) $classroom->provider,
            status: $classroom->status->value,
            healthStatus: $classroom->health_status->value,
            provisionAttempts: (int) $classroom->provision_attempts,
            createdRemoteAt: $classroom->created_remote_at?->toIso8601String(),
            startedAt: $classroom->started_at?->toIso8601String(),
            endedAt: $classroom->ended_at?->toIso8601String(),
            lastError: $classroom->last_error,
            lastErrorAt: $classroom->last_error_at?->toIso8601String(),
            maxConcurrentParticipants: (int) $classroom->max_concurrent_participants,
            events: $events,
        );
    }
}
