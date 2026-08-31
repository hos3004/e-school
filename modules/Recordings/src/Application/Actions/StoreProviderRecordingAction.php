<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Actions;

use Modules\Recordings\Domain\Models\Recording;

/**
 * إدخال تسجيل جاهز من مزوّد الفصل بصورة idempotent.
 */
final readonly class StoreProviderRecordingAction
{
    public function __construct(
        private RegisterRecordingAction $registerRecording,
        private MarkRecordingReadyAction $markReady,
    ) {}

    public function execute(
        string $organizationId,
        string $sessionId,
        string $classroomId,
        string $provider,
        string $externalRecordingId,
        string $playbackUrl,
        ?int $durationSeconds = null,
    ): Recording {
        /** @var Recording|null $existing */
        $existing = Recording::query()
            ->where('provider', $provider)
            ->where('external_recording_id', $externalRecordingId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $recording = $this->registerRecording->execute(
            organizationId: $organizationId,
            sessionId: $sessionId,
            classroomId: $classroomId,
            provider: $provider,
            externalRecordingId: $externalRecordingId,
            disk: 'remote',
            path: $playbackUrl,
            durationSeconds: $durationSeconds,
        );

        return $this->markReady->execute(
            recording: $recording,
            durationSeconds: $durationSeconds,
        );
    }
}
