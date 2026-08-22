<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

/**
 * حُذف التسجيل قبل انتهاء مدته — طلب اعتراض أو قرار إداري.
 *
 * الحذف تعليق (SoftDeletes) مع سبب موثّق إجباريًا.
 */
final class RecordingDeleted extends RecordingEvent
{
    public function __construct(
        string $recordingId,
        string $organizationId,
        string $sessionId,
        public readonly string $deletedById,
        public readonly string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($recordingId, $organizationId, $sessionId, $actorId, $correlationId);
    }

    public function name(): string
    {
        return 'recordings.deleted';
    }

    public function payload(): array
    {
        return [
            'recording_id' => $this->recordingId,
            'organization_id' => $this->organizationId,
            'session_id' => $this->sessionId,
            'deleted_by' => $this->deletedById,
            'reason' => $this->reason,
        ];
    }
}
