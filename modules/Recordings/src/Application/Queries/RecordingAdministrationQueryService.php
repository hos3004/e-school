<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingAccessGrant;
use Modules\Recordings\Domain\Models\RecordingView;
use Modules\Recordings\Domain\ValueObjects\RecordingAdministrationData;

final readonly class RecordingAdministrationQueryService implements RecordingAdministrationQueries
{
    public function findForOrganization(
        string $organizationId,
        string $recordingId,
    ): ?RecordingAdministrationData {
        $recording = Recording::query()
            ->forOrganization($organizationId)
            ->whereKey($recordingId)
            ->first();

        if ($recording === null) {
            return null;
        }

        return $this->hydrate(new Collection([$recording]))[0];
    }

    public function forSession(string $organizationId, string $sessionId): array
    {
        return $this->hydrate(Recording::query()
            ->forOrganization($organizationId)
            ->forSession($sessionId)
            ->latest('available_from')
            ->get());
    }

    public function hasActiveGrantFor(
        string $organizationId,
        string $recordingId,
        string $userId,
        array $groupIds,
    ): bool {
        return RecordingAccessGrant::query()
            ->where('organization_id', $organizationId)
            ->where('recording_id', $recordingId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->where(static function ($query) use ($userId, $groupIds): void {
                $query->where('granted_to_user_id', $userId);
                if ($groupIds !== []) {
                    $query->orWhereIn('granted_to_group_id', $groupIds);
                }
            })
            ->exists();
    }

    /**
     * @param Collection<int, Recording> $recordings
     * @return list<RecordingAdministrationData>
     */
    private function hydrate(Collection $recordings): array
    {
        if ($recordings->isEmpty()) {
            return [];
        }

        $ids = $recordings->modelKeys();
        $now = now('UTC');
        $grantCounts = RecordingAccessGrant::query()
            ->whereIn('recording_id', $ids)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->selectRaw('recording_id, count(*) as aggregate')
            ->groupBy('recording_id')
            ->pluck('aggregate', 'recording_id');
        $viewMetrics = RecordingView::query()
            ->whereIn('recording_id', $ids)
            ->selectRaw("recording_id, count(*) filter (where action = 'view') as view_count, count(*) filter (where action = 'download') as download_count, max(viewed_at) as last_viewed_at")
            ->groupBy('recording_id')
            ->get()
            ->keyBy('recording_id');

        return $recordings->map(static function (Recording $recording) use ($grantCounts, $viewMetrics): RecordingAdministrationData {
            /** @var RecordingView|null $metrics */
            $metrics = $viewMetrics[(string) $recording->getKey()] ?? null;

            return new RecordingAdministrationData(
                id: (string) $recording->getKey(),
                organizationId: (string) $recording->organization_id,
                sessionId: (string) $recording->session_id,
                classroomId: (string) $recording->classroom_id,
                provider: (string) $recording->provider,
                status: $recording->status->value,
                durationSeconds: $recording->duration_seconds,
                sizeBytes: $recording->size_bytes,
                availableFrom: $recording->available_from->toIso8601String(),
                expiresAt: $recording->expires_at->toIso8601String(),
                archivedAt: $recording->archived_at?->toIso8601String(),
                activeGrantCount: (int) ($grantCounts[(string) $recording->getKey()] ?? 0),
                viewCount: $metrics === null ? 0 : (int) $metrics->view_count,
                downloadCount: $metrics === null ? 0 : (int) $metrics->download_count,
                lastViewedAt: $metrics?->last_viewed_at === null ? null : (string) $metrics->last_viewed_at,
            );
        })->values()->all();
    }
}
