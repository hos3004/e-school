<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

/**
 * شاهد مستخدم تسجيلًا أو نزّله — يُسجَّل كل وصول للتدقيق.
 */
final class RecordingViewed extends RecordingEvent
{
    public function __construct(
        string $recordingId,
        string $organizationId,
        string $sessionId,
        public readonly string $userId,
        public readonly string $action,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($recordingId, $organizationId, $sessionId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'recordings.viewed';
    }

    public function payload(): array
    {
        return [
            'recording_id' => $this->recordingId,
            'organization_id' => $this->organizationId,
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'action' => $this->action,
        ];
    }
}
