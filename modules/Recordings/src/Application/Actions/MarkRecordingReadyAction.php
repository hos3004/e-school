<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Recordings\Application\Concerns\TransitionsRecordingStatus;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingBecameReady;
use Modules\Recordings\Domain\Models\Recording;
use Shared\Support\Transaction;

/**
 * إعلان جاهزية التسجيل للمشاهدة بعد معالجة المزوّد.
 *
 * الانتقال processing → ready يمر عبر canTransitionTo حتمًا.
 */
final readonly class MarkRecordingReadyAction
{
    use TransitionsRecordingStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Recording $recording,
        ?int $durationSeconds = null,
        ?int $sizeBytes = null,
        ?string $thumbnailPath = null,
        ?string $actorId = null,
        ?string $reason = null,
    ): Recording {
        $this->transaction->run(function () use ($recording, $durationSeconds, $sizeBytes, $thumbnailPath, $actorId, $reason): void {
            $oldStatus = $recording->status->value;
            $this->applyTransition($recording, RecordingStatus::Ready, array_filter([
                'duration_seconds' => $durationSeconds,
                'size_bytes' => $sizeBytes,
                'thumbnail_path' => $thumbnailPath,
            ], static fn (mixed $value): bool => $value !== null));

            $this->audit->record(
                organizationId: (string) $recording->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'integration' : 'user',
                action: 'recordings.ready',
                auditableType: 'recordings',
                auditableId: (string) $recording->getKey(),
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => RecordingStatus::Ready->value,
                    'duration_seconds' => $recording->duration_seconds,
                    'size_bytes' => $recording->size_bytes,
                ],
                reason: trim($reason ?? '') ?: (string) __('recordings::messages.processing_completed_reason'),
            );
        });

        $this->events->dispatch(new RecordingBecameReady(
            recordingId: $recording->id,
            organizationId: $recording->organization_id,
            sessionId: $recording->session_id,
            durationSeconds: $recording->duration_seconds,
            sizeBytes: $recording->size_bytes,
            actorId: $actorId,
        ));

        return $recording->refresh();
    }
}
