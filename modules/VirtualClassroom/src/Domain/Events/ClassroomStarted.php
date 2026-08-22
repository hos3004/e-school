<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

/**
 * فُتح الفصل فعليًا عند المزوّد وبدأت الحصة المباشرة.
 */
final class ClassroomStarted extends VirtualClassroomEvent
{
    public function __construct(
        string $classroomId,
        string $sessionId,
        string $provider,
        public readonly string $startedAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($classroomId, $sessionId, $provider, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'virtualclassroom.classroom_started';
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
            'started_at' => $this->startedAt,
        ];
    }
}
