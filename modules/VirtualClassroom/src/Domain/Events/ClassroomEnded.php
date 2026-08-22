<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

/**
 * أُغلق الفصل وطُرد جميع المشاركين.
 */
final class ClassroomEnded extends VirtualClassroomEvent
{
    public function __construct(
        string $classroomId,
        string $sessionId,
        string $provider,
        public readonly string $endedAt,
        public readonly int $maxConcurrentParticipants,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($classroomId, $sessionId, $provider, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'virtualclassroom.classroom_ended';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'classroom_id' => $this->classroomId,
            'session_id' => $this->sessionId,
            'provider' => $this->provider,
            'ended_at' => $this->endedAt,
            'max_concurrent_participants' => $this->maxConcurrentParticipants,
        ];
    }
}
