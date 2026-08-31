<?php

declare(strict_types=1);

namespace App\Services\VirtualClassroom;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Recordings\Application\Actions\StoreProviderRecordingAction;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\ValueObjects\RecordingHandle;
use Throwable;

/**
 * طبقة تركيب التطبيق بين BBB وRecordings.
 *
 * المزوّد لا يعرف نموذج التسجيل، وموديول التسجيل لا يعرف تفاصيل BBB؛
 * هذه الطبقة تختار رابط تشغيل آمنًا وتحفظه من خلال Action المالك.
 */
final readonly class RecordingSynchronizer
{
    public function __construct(
        private VirtualClassroomProvider $provider,
        private StoreProviderRecordingAction $storeRecording,
    ) {}

    public function syncClassroom(string $classroomId): int
    {
        $context = DB::table('classrooms')
            ->join('sessions', 'sessions.id', '=', 'classrooms.session_id')
            ->where('classrooms.id', $classroomId)
            ->whereNull('sessions.deleted_at')
            ->first([
                'classrooms.id as classroom_id',
                'classrooms.external_id',
                'classrooms.provider',
                'sessions.id as session_id',
                'sessions.organization_id',
            ]);

        if ($context === null || (string) $context->provider !== $this->provider->name()) {
            return 0;
        }

        $stored = 0;

        foreach ($this->provider->recordings((string) $context->external_id) as $recording) {
            $playbackUrl = $this->playbackUrl($recording);

            if ($recording->recordingId === '' || $playbackUrl === null) {
                continue;
            }

            $this->storeRecording->execute(
                organizationId: (string) $context->organization_id,
                sessionId: (string) $context->session_id,
                classroomId: (string) $context->classroom_id,
                provider: (string) $context->provider,
                externalRecordingId: $recording->recordingId,
                playbackUrl: $playbackUrl,
                durationSeconds: $this->durationSeconds($recording),
            );
            $stored++;
        }

        return $stored;
    }

    public function syncKnownClassrooms(): int
    {
        $count = 0;

        DB::table('classrooms')
            ->where('provider', $this->provider->name())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (string $id) use (&$count): void {
                try {
                    $count += $this->syncClassroom($id);
                } catch (Throwable $exception) {
                    Log::warning('virtualclassroom.recording_sync_failed', [
                        'classroom_id' => $id,
                        'provider' => $this->provider->name(),
                        'exception' => $exception::class,
                    ]);
                }
            });

        return $count;
    }

    private function playbackUrl(RecordingHandle $recording): ?string
    {
        foreach ($recording->formats as $format) {
            $url = $format['url'] ?? null;

            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                return $url;
            }
        }

        return null;
    }

    private function durationSeconds(RecordingHandle $recording): ?int
    {
        $seconds = 0;

        foreach ($recording->formats as $format) {
            $seconds = max($seconds, (int) ($format['length'] ?? 0));
        }

        return $seconds > 0 ? $seconds : null;
    }
}
