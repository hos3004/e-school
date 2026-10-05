<?php

declare(strict_types=1);

namespace Modules\Students\Domain\ValueObjects;

final readonly class StudentDirectoryData
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $userId,
        public string $studentCode,
        public ?string $joinedAt,
        public bool $archived,
    ) {}
}
