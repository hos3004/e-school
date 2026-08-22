<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Recordings\Application\Concerns\TransitionsRecordingStatus;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingArchived;
use Modules\Recordings\Domain\Models\Recording;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * نقل التسجيل إلى الأرشيف البارد.
 *
 * السائق الافتراضي من config('recordings.storage.archive_driver').
 */
final readonly class ArchiveRecordingAction
{
    use TransitionsRecordingStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        Recording $recording,
        ?string $archiveDriver = null,
        ?string $archivePath = null,
        ?string $actorId = null,
    ): Recording {
        $driver = $archiveDriver ?? (string) config('recordings.storage.archive_driver');

        if ($driver === '' || $driver === 'none') {
            throw BusinessRuleViolation::make(
                'recordings.archive_driver_missing',
                'recordings::errors.archive_driver_missing',
            );
        }

        $archivedAt = CarbonImmutable::now('UTC');

        $this->transaction->run(function () use ($recording, $driver, $archivePath, $archivedAt): void {
            $this->applyTransition($recording, RecordingStatus::Archived, [
                'archive_driver' => $driver,
                'archive_path' => $archivePath,
                'archived_at' => $archivedAt,
            ]);
        });

        $this->events->dispatch(new RecordingArchived(
            recordingId: $recording->id,
            organizationId: $recording->organization_id,
            sessionId: $recording->session_id,
            archiveDriver: $driver,
            archivedAt: $archivedAt->toIso8601String(),
            actorId: $actorId,
        ));

        return $recording->refresh();
    }
}
