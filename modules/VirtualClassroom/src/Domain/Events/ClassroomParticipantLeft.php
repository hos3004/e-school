<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

/**
 * غادر مشارك الفصل المباشر.
 */
final class ClassroomParticipantLeft extends VirtualClassroomEvent
{
    public readonly string $participantOccurredAt;

    public function __construct(
        string $classroomId,
        string $sessionId,
        string $provider,
        public readonly string $externalUserId,
        public readonly ?string $userId,
        string $occurredAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        $this->participantOccurredAt = $occurredAt;
        parent::__construct($classroomId, $sessionId, $provider, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'virtualclassroom.participant_left';
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
            'occurred_at' => $this->participantOccurredAt,
        ];
    }
}
