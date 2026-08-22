<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

/**
 * نُقل التسجيل إلى الأرشيف البارد بعد انتهاء مدة الاحتفاظ.
 */
final class RecordingArchived extends RecordingEvent
{
    public function __construct(
        string $recordingId,
        string $organizationId,
        string $sessionId,
        public readonly string $archiveDriver,
        public readonly string $archivedAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($recordingId, $organizationId, $sessionId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'recordings.archived';
    }

    public function payload(): array
    {
        return [
            'recording_id' => $this->recordingId,
            'organization_id' => $this->organizationId,
            'session_id' => $this->sessionId,
            'archive_driver' => $this->archiveDriver,
            'archived_at' => $this->archivedAt,
        ];
    }
}
