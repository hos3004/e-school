<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

use Modules\VirtualClassroom\Domain\Enums\JoinRole;

/**
 * انضم مشارك إلى الفصل المباشر.
 */
final class ClassroomParticipantJoined extends VirtualClassroomEvent
{
    public readonly string $participantOccurredAt;

    public function __construct(
        string $classroomId,
        string $sessionId,
        string $provider,
        public readonly string $externalUserId,
        public readonly ?string $userId,
        public readonly JoinRole $role,
        string $occurredAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        $this->participantOccurredAt = $occurredAt;
        parent::__construct($classroomId, $sessionId, $provider, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'virtualclassroom.participant_joined';
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
            'external_user_id' => $this->externalUserId,
            'user_id' => $this->userId,
            'role' => $this->role->value,
            'occurred_at' => $this->participantOccurredAt,
        ];
    }
}
