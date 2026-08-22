<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

/**
 * أصبح التسجيل جاهزًا للمشاهدة بعد معالجة المزوّد.
 */
final class RecordingBecameReady extends RecordingEvent
{
    public function __construct(
        string $recordingId,
        string $organizationId,
        string $sessionId,
        public readonly ?int $durationSeconds,
        public readonly ?int $sizeBytes,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($recordingId, $organizationId, $sessionId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'recordings.became_ready';
    }

    public function payload(): array
    {
        return [
            'recording_id' => $this->recordingId,
            'organization_id' => $this->organizationId,
            'session_id' => $this->sessionId,
            'duration_seconds' => $this->durationSeconds,
            'size_bytes' => $this->sizeBytes,
        ];
    }
}
