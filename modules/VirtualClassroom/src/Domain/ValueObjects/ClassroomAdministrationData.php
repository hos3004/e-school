<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

final readonly class ClassroomAdministrationData
{
    /** @param list<array<string, mixed>> $events */
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $provider,
        public string $status,
        public string $healthStatus,
        public int $provisionAttempts,
        public ?string $createdRemoteAt,
        public ?string $startedAt,
        public ?string $endedAt,
        public ?string $lastError,
        public ?string $lastErrorAt,
        public int $maxConcurrentParticipants,
        public array $events,
    ) {}
}
