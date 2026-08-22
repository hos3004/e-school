<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;

/**
 * فُحصت صحة الفصل وتغيّرت حالته.
 */
final class ClassroomHealthChecked extends VirtualClassroomEvent
{
    public function __construct(
        string $classroomId,
        string $sessionId,
        string $provider,
        public readonly ClassroomHealthStatus $status,
        public readonly ?string $message = null,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($classroomId, $sessionId, $provider, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'virtualclassroom.health_checked';
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
            'health_status' => $this->status->value,
            'requires_attention' => $this->status->requiresAttention(),
            'message' => $this->message,
        ];
    }
}
