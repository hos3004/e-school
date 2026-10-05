<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

final readonly class PersistentRoomData
{
    public function __construct(
        public string $scheduleId,
        public string $label,
        public string $status,
        public int $generation,
        public ?string $rotatedAt,
        public ?string $lastErrorAt,
    ) {}
}
