<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

/**
 * انتهت مدة الاحتفاظ بالتسجيل وحُذف نهائيًا من التخزين.
 */
final class RecordingExpired extends RecordingEvent
{
    public function __construct(
        string $recordingId,
        string $organizationId,
        string $sessionId,
        public readonly string $expiresAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($recordingId, $organizationId, $sessionId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'recordings.expired';
    }

    public function payload(): array
    {
        return [
            'recording_id' => $this->recordingId,
            'organization_id' => $this->organizationId,
            'session_id' => $this->sessionId,
            'expires_at' => $this->expiresAt,
        ];
    }
}
