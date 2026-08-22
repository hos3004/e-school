<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

/**
 * سُجّل ملف تسجيل لحصة — رُبط بالمزوّد وحُدّدت مدة الاحتفاظ.
 */
final class RecordingRegistered extends RecordingEvent
{
    public function __construct(
        string $recordingId,
        string $organizationId,
        string $sessionId,
        public readonly string $classroomId,
        public readonly string $provider,
        public readonly string $externalRecordingId,
        public readonly string $expiresAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($recordingId, $organizationId, $sessionId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'recordings.registered';
    }

    public function payload(): array
    {
        return [
            'recording_id' => $this->recordingId,
            'organization_id' => $this->organizationId,
            'session_id' => $this->sessionId,
            'classroom_id' => $this->classroomId,
            'provider' => $this->provider,
            'external_recording_id' => $this->externalRecordingId,
            'expires_at' => $this->expiresAt,
        ];
    }
}
