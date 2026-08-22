<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

/**
 * أُنشئ الفصل المباشر عند المزوّد وارتبط بحصة.
 */
final class ClassroomProvisioned extends VirtualClassroomEvent
{
    public function __construct(
        string $classroomId,
        string $sessionId,
        string $provider,
        public readonly string $externalId,
        public readonly string $createdRemoteAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($classroomId, $sessionId, $provider, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'virtualclassroom.classroom_provisioned';
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
            'external_id' => $this->externalId,
            'created_remote_at' => $this->createdRemoteAt,
        ];
    }
}
