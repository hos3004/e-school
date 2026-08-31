<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\ValueObjects;

final readonly class RecordingAdministrationData
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $sessionId,
        public string $classroomId,
        public string $provider,
        public string $status,
        public ?int $durationSeconds,
        public ?int $sizeBytes,
        public string $availableFrom,
        public string $expiresAt,
        public ?string $archivedAt,
        public int $activeGrantCount,
        public int $viewCount,
        public int $downloadCount,
        public ?string $lastViewedAt,
    ) {}
}
